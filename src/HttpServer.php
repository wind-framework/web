<?php

namespace Wind\Web;

use FastRoute\Dispatcher;
use Invoker\Invoker;
use Invoker\ParameterResolver\{
    AssociativeArrayResolver,
    Container\TypeHintContainerResolver,
    DefaultValueResolver,
    ResolverChain,
    TypeHintResolver
};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Wind\Base\{
    Application,
    Event\SystemError,
    Exception\CallableException,
    Exception\ExitException
};
use Wind\Web\Exception\HttpException;
use Wind\Web\Stream\StreamingInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\{
    Chunk,
    Request as RawRequest,
    Response as RawResponse
};
use Workerman\Worker;

class HttpServer extends Worker
{

    public $name = 'HttpServer';

    /**
     * @var Router
     */
    private $router;

    /**
     * @var Application
     */
    private $app;

    /**
     * @var Invoker
     */
    public $invoker;

    /**
     * Middlewares
     *
     * @var MiddlewareInterface[]
     */
    public $middlewares = [];

    public function __construct($socket_name = '', array $config = [])
    {
        parent::__construct('http://'.$socket_name, $config['context_options'] ?? []);

        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = asyncCallable([$this, 'onMessage']);

        $this->app = Application::getInstance();

        //初始化依赖注入 callable Invoker
        //此 Invoker 主要加入了 TypeHintResolver，可在调用时根据类型注入临时的 Request 等
        //否则直接使用 $this->container->call()
        $parameterResolver = new ResolverChain(array(
            new AssociativeArrayResolver,
            new DefaultValueResolver,
            new TypeHintResolver,
            new TypeHintContainerResolver($this->app->container)
        ));

        $this->invoker = new Invoker($parameterResolver, $this->app->container);

        //Router
        $this->router = new Router($config['router'] ?? 'routes');
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStart($worker)
    {
        //Middlewares
        //User business code must in worker, not main process, to prevent some initialize from main process
        $middlewares = $this->app->config->get('middlewares');

        if ($middlewares) {
            foreach ($middlewares as $middleware) {
                $this->middlewares[] = $this->app->container->make($middleware);
            }
        }

        $this->app->startComponents($worker);
        $this->app->container->set(Router::class, $this->router);
    }

    /**
     * @param TcpConnection $connection
     * @param RawRequest $request
     */
    public function onMessage($connection, $request)
    {
        $routeInfo = $this->router->dispatch($request->method(), $request->path());

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                [, $target, $vars] = $routeInfo;
                try {
                    //Todo: 由于 wrapCallable 并没有使用 $this->invoker，所以在控制器的构造函数中并不能获取 Request，但理论上到了控制器层面就可以获取了，有改进空间，比如允许 wrapCallable 传递 Invoker
                    $callable = wrapCallable($target['handler'], false);
                } catch (CallableException $e) {
                    $this->sendServerError($connection, $e);
                    return;
                }

                $vars[RawRequest::class] = $request;
                $vars[RequestInterface::class] = new Request($request, $connection);

                $action = new Action($callable, $vars, $this->invoker, $this->middlewares, $target['middlewares'] ?? []);

                try {
                    $response = $action($vars[RequestInterface::class]);

                    if ($response instanceof ResponseInterface) {
                        if ($response->hasHeader('X-Workerman-Sendfile')) {
                            //X-Workerman-Sendfile supported.
                            $sendFile = $response->getHeaderLine('X-Workerman-Sendfile');
                            $headers = $response->withoutHeader('X-Workerman-Sendfile')->getHeaders();
                            $headers = $this->flattenHeaders($headers);
                            $response = (new RawResponse(200, $headers))->withFile($sendFile);
                        } else {
                            $body = $response->getBody();
                            $headers = $this->flattenHeaders($response->getHeaders());

                            if ($body instanceof StreamingInterface) {
                                //Streamed response
                                $wrapper = $response->getHeaderLine('Transfer-Encoding') == 'chunked' ? Chunk::class : Buffer::class;
                                $response = new RawResponse($response->getStatusCode(), $headers, "\r\n");
                                $connection->send($response);

                                while (null !== $buffer = $body->read()) {
                                    if ($connection->send(new $wrapper($buffer)) === false) {
                                        //stop send when connection is closed or buffer is full
                                        break;
                                    }
                                }

                                //End chunked response
                                if ($wrapper == Chunk::class) {
                                    $connection->send(new Chunk(''));
                                } else {
                                    $connection->close();
                                }

                                $body->close();
                                return;

                            } else {
                                $contents = (string)$body;
                                $body->close();
                                $response = new RawResponse($response->getStatusCode(), $headers, $contents);
                            }
                        }
                    }

                    $connection->send($response);

                } catch (ExitException $e) {
                    $connection->send('');
                } catch (\Throwable $e) {
                    $eventDispatcher = $this->app->container->get(EventDispatcherInterface::class);
                    $this->sendServerError($connection, $e);
                    $eventDispatcher->dispatch(new SystemError($e));
                }
                break;

            case Dispatcher::NOT_FOUND:
                $this->sendPageNotFound($connection);
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                $connection->send(new RawResponse(405, [], 'Method Not Allowed'));
                break;
        }
    }

	/**
	 * @param TcpConnection $connection
	 */
    public function sendPageNotFound($connection) {
	    $connection->send(new RawResponse(404, [], "<h1>404 Not Found</h1><p>The page you looking for is not found.</p>"));
    }

    /**
     * @param TcpConnection $connection
     * @param \Throwable $e
     */
    public function sendServerError($connection, $e) {
        $headers = $e instanceof HttpException ? $e->headers : [];
        $connection->send(new RawResponse(500, $headers, '<h1>'.get_class($e).': '.$e->getMessage().'</h1>'
            .'<p>in '.$e->getFile().':'.$e->getLine().'</p>'
            .'<b>Stack trace:</b><pre>'.$e->getTraceAsString().'</pre>'));
    }

    private function flattenHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $key => $value) {
            $result[$key] = current($value);
        }

        return $result;
    }

}
