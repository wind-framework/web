<?php

namespace Wind\Web;

use FastRoute\Dispatcher;
use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintResolver;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Wind\Base\Application;
use Wind\Base\Event\SystemError;
use Wind\Base\Exception\CallableException;
use Wind\Base\Exception\ExitException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Worker;
use function Amp\call;

class HttpServer extends Worker
{

    /**
     * @var Dispatcher
     */
    private $dispatcher;

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

    public function __construct($socket_name = '', array $context_option = array())
    {
        parent::__construct('http://'.$socket_name, $context_option);

        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        $this->app = Application::getInstance();

        //初始化路由
        $route = $this->app->config->get('route');
        $this->dispatcher = \FastRoute\simpleDispatcher($route);

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
    }

    /**
     * @param TcpConnection $connection
     * @param WorkermanRequest $request
     */
    public function onMessage($connection, $request)
    {
        $routeInfo = $this->dispatcher->dispatch($request->method(), $request->path());

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                list(, $handler, $vars) = $routeInfo;
                try {
                    $callable = wrapCallable($handler, false);
                } catch (CallableException $e) {
                    $this->sendServerError($connection, $e);
                    return;
                }

                $vars[WorkermanRequest::class] = $request;
                $vars[RequestInterface::class] = new Request($request, $connection);

                $action = new Action($callable, $vars, $this);

                call($action, $vars[RequestInterface::class])->onResolve(function($e, $response) use ($connection) {
                    if ($e === null) {
                        if ($response instanceof ResponseInterface) {
                            //X-Workerman-Sendfile supported.
                            if ($response->hasHeader('X-Workerman-Sendfile')) {
                                $sendFile = $response->getHeaderLine('X-Workerman-Sendfile');
                                $response = (new WorkermanResponse())->withFile($sendFile);
                            } else {
                                $body = $response->getBody();
                                $contents = $body->__toString();
                                $body->close();

                                $response = new WorkermanResponse(
                                    $response->getStatusCode(),
                                    $response->getHeaders(),
                                    $contents
                                );
                            }
                        }

                        $connection->send($response);

                    } elseif ($e instanceof ExitException) {
                        $connection->send('');
                    } else {
                        $eventDispatcher = $this->app->container->get(EventDispatcherInterface::class);
                        if ($e instanceof \Exception) {
                            $this->sendServerError($connection, $e);
                            $eventDispatcher->dispatch(new SystemError($e));
                        } else {
                            $eventDispatcher->dispatch(new SystemError($e));
                            throw $e;
                        }
                    }
                });
                break;
            case Dispatcher::NOT_FOUND:
                $this->sendPageNotFound($connection);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                $connection->send(new WorkermanResponse(405, [], 'Method Not Allowed'));
                break;
        }
    }

	/**
	 * @param TcpConnection $connection
	 */
    public function sendPageNotFound($connection) {
	    $connection->send(new WorkermanResponse(404, [], "<h1>404 Not Found</h1><p>The page you looking for is not found.</p>"));
    }

    /**
     * @param TcpConnection $connection
     * @param \Throwable $e
     */
    public function sendServerError($connection, $e) {
        $connection->send(new WorkermanResponse(500, [], '<h1>'.get_class($e).': '.$e->getMessage().'</h1>'
            .'<p>in '.$e->getFile().':'.$e->getLine().'</p>'
            .'<b>Stack trace:</b><pre>'.$e->getTraceAsString().'</pre>'));
    }
}