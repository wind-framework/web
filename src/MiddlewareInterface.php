<?php

namespace Wind\Web;

use Psr\Http\Message\ServerRequestInterface;
use Workerman\Protocols\Http\Response;

interface MiddlewareInterface
{

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     * 
     * @return \Amp\Promise<mixed>
     */
    public function process(ServerRequestInterface $request, callable $handler);

}
