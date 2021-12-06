<?php
/**
 * Wind Framework
 */
namespace Wind\Web;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Application;
use Wind\Base\Event\SystemError;
use Wind\Web\Exception\WebSocketException;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

/**
 * WebSocket Server
 */
class WebSocketServer extends Worker
{

    public $name = 'WebSocketServer';

    /**
     * @var Application
     */
    private $app;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct($socket_name = '', array $context_option = array())
    {
        parent::__construct('websocket://'.$socket_name, $context_option);

        $this->app = Application::getInstance();

        //Router dispatcher
        $this->dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $collector) {
            $routes = config('websocket.routes', []);
            foreach ($routes as $path => $controller) {
                $collector->get($path, $controller);
            }
        });;

        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onWebSocketConnect = asyncCallable([$this, 'onWebSocketConnect']);
        $this->app = Application::getInstance();
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
     * @param string $headers
     */
    public function onWebSocketConnect($connection, $headers)
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $routeInfo = $this->dispatcher->dispatch('GET', $path);

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                list(, $handler, $vars) = $routeInfo;

                /**
                 * @var WebsocketInterface $controller
                 */
                $controller = $this->app->container->get($handler);
                //Todo: May be block because of onConnect is not async call in main stack.
                $controller->onConnect($connection, $vars);
                $connection->onMessage = asyncCallable([$controller, 'onMessage']);
                $connection->onClose = asyncCallable([$controller, 'onClose']);
                break;
            case Dispatcher::NOT_FOUND:
                $eventDispatcher = $this->app->container->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new SystemError(new WebSocketException("Not found router for path: $path")));
            default:
                $connection->close();
        }
    }

}
