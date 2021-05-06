<?php

namespace Wind\Web;

use Psr\Http\Message\UriInterface;
use Wind\Web\Common\MessageTrait;
use Workerman\Connection\TcpConnection;

/**
 * Wind Request Adapter with Workerman Request
 * @package Wind\Web
 */
class Request implements RequestInterface
{

    use MessageTrait;

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
    protected $host;
    protected $method;
    protected $uri;
    protected $cookies;
    protected $queryParams;
    protected $parsedBody;
    protected $uploadedFiles;
    protected $attributes = [];
    
    public function __construct(\Workerman\Protocols\Http\Request $request, TcpConnection $connection)
    {
        $this->request = $request;
        $this->connection = $connection;
    }

    public function get($key, $defaultValue = null) {
        return $this->request->get($key, $defaultValue);
    }

    public function post($key, $defaultValue = null) {
        return $this->request->post($key, $defaultValue);
    }

    public function cookie($key, $defaultValue = null) {
        return $this->request->cookie($key, $defaultValue);
    }

    public function file($key) {
        return $this->request->file($key);
    }

    public function getProtocolVersion()
    {
        return $this->protocolVersion ?: $this->request->protocolVersion();
    }

    /**
     * @inheritDoc
     */
    public function getHeaders()
    {
        if ($this->headers === null) {
            $this->headers = $this->request->header();
        }

        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function getBody()
    {
        if ($this->body === null) {
            $this->body = StreamBody::create($this->request->rawBody());
        }
        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget()
    {
        return $this->requestTarget ?: $this->request->uri();
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget($requestTarget)
    {
        $req = clone $this;
        $req->requestTarget = $requestTarget;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getMethod()
    {
        return $this->method ?: $this->request->method();
    }

    /**
     * @inheritDoc
     */
    public function withMethod($method)
    {
        if (!in_array(strtolower($method), ['get', 'head', 'post', 'put', 'delete', 'connect', 'options', 'trace', 'patch'])) {
            throw new \InvalidArgumentException("Invalid method '$method'.");
        }

        $req = clone $this;
        $req->method = $method;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getUri()
    {
        if ($this->uri === null) {
            $this->uri = new Uri($this->request, $this->connection);
        }

        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($preserveHost && $uri->getHost() == '' && ($host = $this->getUri()->getHost()) != '') {
            $uri->withHost($host);
        }

        $req = clone $this;
        $req->uri = $uri;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getServerParams()
    {
        return $_SERVER;
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams()
    {
        return $this->cookies ?: $this->request->cookie();
    }

    /**
     * @inheritDoc
     */
    public function withCookieParams(array $cookies)
    {
        $req = clone $this;
        $req->cookies = $cookies;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getQueryParams()
    {
        return $this->queryParams ?: $this->request->get();
    }

    /**
     * @inheritDoc
     */
    public function withQueryParams(array $query)
    {
        $req = clone $this;
        $req->queryParams = $query;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getUploadedFiles()
    {
        if ($this->uploadedFiles) {
            return $this->uploadedFiles;
        }

        $uploadedFiles = [];
        $files = $this->request->file();

        if ($files) {
            foreach ($files as $name => $file) {
                $uploadFile = new UploadedFile($file);
                $uploadedFiles[] = $uploadFile;
            }
        }

        return $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * Get an uploaded file
     *
     * @param string $name
     * @return UploadedFile|null
     */
    public function getUploadedFile($name)
    {
        $uplaodFiles = $this->getUploadedFiles();
        return $uplaodFiles[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $req = clone $this;
        $req->uploadedFiles = $uploadedFiles;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getParsedBody()
    {
        return $this->parsedBody ?: $this->request->post();
    }

    /**
     * @inheritDoc
     */
    public function withParsedBody($data)
    {
        $req = clone $this;
        $req->parsedBody = $data;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * @inheritDoc
     */
    public function withAttribute($name, $value)
    {
        $req = clone $this;
        $req->attributes[$name] = $value;
        return $req;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute($name)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $req = clone $this;
        unset($req->attributes[$name]);
        return $req;
    }

}