<?php

namespace Wind\Web;

use Workerman\Protocols\Http\Response as WorkermanResponse;

use function Amp\call;

class Action implements MiddlewareInterface
{

    /**
     * @var callable
     */
    public $action;

    /**
     * Action Parameters
     *
     * @var array
     */
    public $vars;

    /**
     * @var callable
     */
    private $invoker;

    /**
     * @var MiddlewareInterface[]
     */
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

        $this->invoker = [$httpServer->invoker, 'call'];

        $this->middlewares = $httpServer->middlewares;
        $this->middlewares[]  = $this; //<- cycles reference

        $this->isController = (is_array($this->action) && is_object($this->action[0]) && $this->action[0] instanceof Controller);
    }

    public function process(RequestInterface $request, callable $handler)
    {
        //free cycles reference. 
        $this->middlewares = null;

        //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
        if ($this->isController) {
            yield call($this->invoker, [$this->action[0], 'init'], $this->vars);
        }

        $content = yield call($this->invoker, $this->action, $this->vars);

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

    public function __invoke(RequestInterface $request)
    {
        $middleware = current($this->middlewares);
        next($this->middlewares);
        return call([$middleware, 'process'], $request, $this);
    }

}