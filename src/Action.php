<?php

namespace Wind\Web;

use Psr\Http\Message\ServerRequestInterface;
use function Amp\call;

class Action implements MiddlewareInterface
{

    public $action;

    /**
     * @var array
     */
    public $vars;

    /**
     * @var HttpServer
     */
    private $httpServer;

    private $middlewares = [];
    private $currentMiddleware = 0;

    /**
     * Action constructor.
     * @param $action
     * @param $vars
     * @param HttpServer $httpServer
     */
    public function __construct($action, $vars, $httpServer)
    {
        $this->action = $action;
        $this->vars = $vars;
        $this->httpServer = $httpServer;
        $this->middlewares = array_merge($httpServer->middlewares, [$this]);
    }

    public function process(ServerRequestInterface $request, callable $handler)
    {
        //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
        if (is_array($this->action) && is_object($this->action[0]) && method_exists($this->action[0], 'init')) {
            yield wireCall([$this->action[0], 'init'], $this->vars, $this->httpServer->invoker);
        }

        return yield wireCall($this->action, $this->vars, $this->httpServer->invoker);
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $middleware = $this->middlewares[$this->currentMiddleware++];
        echo "call ".get_class($middleware)."\n";
        return call([$middleware, 'process'], $request, $this);
    }

}