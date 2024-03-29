<?php
/**
 * Wind Framework
 */
namespace Wind\Web;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Revolt\EventLoop;
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

    /**
     * Callbacks
     * @var callable
     */
    private $onStart;

    public function __construct($socket_name = '', array $config = [])
    {
        parent::__construct('websocket://'.$socket_name, $config['context_option'] ?? []);

        $this->app = Application::getInstance();

        //Router dispatcher
        $this->dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $collector) use ($config) {
            $routes = config($config['router'] ?? 'websocket', []);
            foreach ($routes as $path => $controller) {
                $collector->get($path, $controller);
            }
        });;

        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onWebSocketConnect = asyncCallable([$this, 'onWebSocketConnect']);

        isset($config['on_start']) && $this->onStart = $config['on_start'];

        if (isset($config['on_stop'])) {
            $this->onWorkerStop = $config['on_stop'];
        }

        if (isset($config['on_ping'])) {
            $this->onWebSocketPing = static function($connection, $data) use ($config) {
                //Must response immediately, or else will not send pong.
                $connection->send($data);
                //Async call will run in loop when next tick, it's not immediately.
                EventLoop::queue(static fn() => $config['on_ping']($connection, $data));
            };
        }

        if (isset($config['on_pong'])) {
            $this->onWebSocketPong = asyncCallable($config['on_pong']);
        }

        $this->app = Application::getInstance();
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStart($worker)
    {
        $this->app->startComponents($worker);

        if (isset($this->onStart)) {
            call_user_func($this->onStart, $worker);
        }
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
                EventLoop::queue(static fn() => $controller->onConnect($connection, $vars));
                $connection->onMessage = asyncCallable([$controller, 'onMessage']);
                $connection->onClose = asyncCallable([$controller, 'onClose']);


                if (is_callable([$controller, 'onPing'])) {
                    $connection->onWebSocketPing = static function($connection, $data) use ($controller) {
                        //Must response immediately, or else will not send pong.
                        $connection->send($data);
                        //async call will run in loop when next tick, it's not immediately.
                        EventLoop::queue(static fn() => call_user_func([$controller, 'onPing'], $connection, $data));
                    };
                }

                if (is_callable([$controller, 'onPong'])) {
                    $connection->onWebSocketPong = asyncCallable([$controller, 'onPong']);
                }
                break;
            case Dispatcher::NOT_FOUND:
                $eventDispatcher = $this->app->container->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new SystemError(new WebSocketException("Not found router for path: $path")));
            default:
                $connection->close();
        }
    }

}
