<?php

namespace Wind\Web\Middleware;

use Wind\Log\LogFactory;
use Wind\Web\MiddlewareInterface;
use Wind\Web\RequestInterface;

class AccessLogMiddleware implements MiddlewareInterface
{

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    public function __construct(LogFactory $logFactory)
    {
        $this->logger = $this->getLogger($logFactory);
    }

    /**
     * Customize logger
     *
     * @param LogFactory $logFactory
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger(LogFactory $logFactory)
    {
        return $logFactory->get('access');
    }

    public function process(RequestInterface $request, callable $handler) {
        /**
         * @var \Psr\Http\Message\ResponseInterface $response
         */
        $response = yield $handler($request);

        $uri = $request->getUri();
        $user = $uri->getUserInfo();

        if ($user && ($p = strpos($user, ':')) !== false) {
            $user = substr($user, 0, $p);
        }

        $log = sprintf(
            '%s - %s "%s %s HTTP/%s" %d %d "%s" "%s"',
            $request->getClientIp(),
            $user ?: '-',
            $request->getMethod(),
            $uri->getPath(),
            $request->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getBody()->getSize(),
            $request->getHeaderLine('Referer') ?: '-',
            $request->getHeaderLine('User-Agent')
        );

        $this->logger->info($log);

        return $response;
    }

}
