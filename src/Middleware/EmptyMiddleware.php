<?php

namespace Wind\Web\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Wind\Web\MiddlewareInterface;

class EmptyMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, callable $handler) {
        //
    }

}