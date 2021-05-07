<?php

namespace Wind\Web\Middleware;

use Wind\Web\MiddlewareInterface;
use Wind\Web\RequestInterface;

class EmptyMiddleware implements MiddlewareInterface
{

    public function process(RequestInterface $request, callable $handler) {
        return $handler($request);
    }

}