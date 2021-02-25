<?php

namespace Wind\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Wind\Web\Exception\RequestUnsupportedException;
use Workerman\Connection\TcpConnection;

/**
 * Wind Request Adapter with Workerman Request
 * @package Wind\Web
 */
class Request implements ServerRequestInterface
{

    /**
     * Workerman origin Request instance
     *
     * @var \Workerman\Protocols\Http\Request
     */
    protected $request;

    /**
     * Workerman connection instance
     *
     * @var TcpConnection
     */
    protected $connection;

    protected $requestTarget;
    protected $protocolVersion;
    protected $host;
    protected $method;
    protected $uri;
    protected $cookies;
    protected $queryParams;
    protected $parsedBody;

    public function __construct(\Workerman\Protocols\Http\Request $request, TcpConnection $connection)
    {
        $this->request = $request;
    }

    public function getProtocolVersion()
    {
        return $this->protocolVersion ?: $this->request->protocolVersion();
    }

    public function withProtocolVersion($version)
    {
        $req = clone $this;
        $req->protocolVersion = $version;
        return $req;
    }

    public function getHeaders()
    {
        return $this->request->header();
    }

    public function hasHeader($name)
    {
        return $this->request->header($name) !== null;
    }

    public function getHeader($name)
    {
        $v = $this->request->header($name);
        return $v !== null ? [$v] : [];
    }

    public function getHeaderLine($name)
    {
        return $this->request->header($name);
    }

    public function withHeader($name, $value)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function withAddedHeader($name, $value)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function withoutHeader($name)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function getBody()
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function withBody(StreamInterface $body)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function getRequestTarget()
    {
        return $this->requestTarget ?? $this->request->uri();
    }

    public function withRequestTarget($requestTarget)
    {
        $req = clone $this;
        $req->requestTarget = $requestTarget;
        return $req;
    }

    public function getMethod()
    {
        return $this->method ?: $this->request->method();
    }

    public function withMethod($method)
    {
        if (!in_array(strtolower($method), ['get', 'head', 'post', 'put', 'delete', 'connect', 'options', 'trace', 'patch'])) {
            throw new \InvalidArgumentException("Invalid method '$method'.");
        }

        $req = clone $this;
        $req->method = $method;
        return $req;
    }

    public function getUri()
    {
        if ($this->uri === null) {
            $this->uri = new Uri($this->request, $this->connection);
        }

        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $req = clone $this;
        $req->uri = $uri;

        if ($preserveHost && $this->request->host() == null) {
            $host = $uri->getHost();
            if ($host != null) {
                $req->host = $host;
            }
        }

        return $req;
    }

    public function getServerParams()
    {
        return $_SERVER;
    }

    public function getCookieParams()
    {
        return $this->cookies ?: $this->request->cookie();
    }

    public function withCookieParams(array $cookies)
    {
        $req = clone $this;
        $req->cookies = $cookies;
        return $req;
    }

    public function getQueryParams()
    {
        return $this->queryParams ?: $this->request->get();
    }

    public function withQueryParams(array $query)
    {
        $req = clone $this;
        $req->queryParams = $query;
        return $req;
    }

    public function getUploadedFiles()
    {
        // TODO: Implement getUploadedFiles() method.
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        // TODO: Implement withUploadedFiles() method.
    }

    public function getParsedBody()
    {
        return $this->parsedBody ?: $this->request->post();
    }

    public function withParsedBody($data)
    {
        $req = clone $this;
        $req->parsedBody = $data;
        return $req;
    }

    public function getAttributes()
    {
        return [];
    }

    public function getAttribute($name, $default = null)
    {
        return $default;
    }

    public function withAttribute($name, $value)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

    public function withoutAttribute($name)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Request.');
    }

}