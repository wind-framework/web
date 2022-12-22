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
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as RawRequest;
use Workerman\Protocols\Http\Response as RawResponse;
use Workerman\Worker;

use function Amp\coroutine;

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

        //Middlewares
        $middlewares = $this->app->config->get('middlewares');

        if ($middlewares) {
            foreach ($middlewares as $middleware) {
                $this->middlewares[] = $this->app->container->make($middleware);
            }
        }
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStart($worker)
    {
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
                list(, $target, $vars) = $routeInfo;
                try {
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
                        //X-Workerman-Sendfile supported.
                        if ($response->hasHeader('X-Workerman-Sendfile')) {
                            $sendFile = $response->getHeaderLine('X-Workerman-Sendfile');
                            $headers = $response->withoutHeader('X-Workerman-Sendfile')->getHeaders();
                            $response = (new RawResponse(200, $headers))->withFile($sendFile);
                        } else {
                            $body = $response->getBody();
                            $contents = $body->__toString();
                            $body->close();

                            $response = new RawResponse(
                                $response->getStatusCode(),
                                $response->getHeaders(),
                                $contents
                            );
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
        $connection->send(new RawResponse(500, [], '<h1>'.get_class($e).': '.$e->getMessage().'</h1>'
            .'<p>in '.$e->getFile().':'.$e->getLine().'</p>'
            .'<b>Stack trace:</b><pre>'.$e->getTraceAsString().'</pre>'));
    }
}
