<?php

namespace Wind\Web\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Wind\Web\Exception\HttpException;
use Wind\Web\Response;
use Wind\Web\Stream;

/**
 * JsonMiddleware
 *
 * Transform response to json format.
 *
 * @package Wind\Web\Middleware
 */
class JsonMiddleware implements \Wind\Web\MiddlewareInterface
{

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, callable $handler)
    {
        $jsonOptions = config('server.json_options', 0);

        try {
            /**
             * @var Response $response
             */
            $response = yield $handler($request);

            $contentType = $response->getHeaderLine('Content-Type');

            if (!$contentType || !str_contains($contentType, 'json')) {
                $body = Stream::create(json_encode($response->getBody()->getContents(), $jsonOptions));
                return $response->withBody($body)
                    ->withHeader('content-type', 'application/json; charset=utf-8');
            } else {
                return $response;
            }

        } catch (\Throwable $e) {
            $status = $e instanceof HttpException ? $e->getCode() : 500;

            $content = [
                'status' => $status,
                'message' => $e->getMessage()
            ];

            if (config('debug', false)) {
                $content['file'] = $e->getFile();
                $content['line'] = $e->getLine();
                $content['trace'] = $e->getTrace();
            }

            return new Response($status, json_encode($content, $jsonOptions), [
                'content-type' => 'application/json; charset=utf-8'
            ]);
        }
    }

}