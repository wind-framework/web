<?php

namespace Wind\Web;

use Psr\Http\Message\ServerRequestInterface;
use Workerman\Protocols\Http\Response as WorkermanResponse;

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

    /**
     * @var bool
     */
    private $isController;

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
        $this->isController = (is_array($this->action) && is_object($this->action[0]) && $this->action[0] instanceof Controller);
    }

    public function process(ServerRequestInterface $request, callable $handler)
    {
        //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
        if ($this->isController) {
            yield wireCall([$this->action[0], 'init'], $this->vars, $this->httpServer->invoker);
        }

        $content = yield wireCall($this->action, $this->vars, $this->httpServer->invoker);

        if ($content instanceof Response || $content instanceof WorkermanResponse) {
            return $content;
        }

        //Default array to json response
        if (is_array($content) || is_object($content)) {
            $body = json_encode($content, config('server.json_options', 0));
            return new Response(200, $body, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }

        return new Response(200, $content);
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $middleware = current($this->middlewares);
        next($this->middlewares);
        return call([$middleware, 'process'], $request, $this);
    }

}