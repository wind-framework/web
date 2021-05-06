<?php

namespace Wind\Web\Common;

use Psr\Http\Message\StreamInterface;

trait MessageTrait
{

    /**
     * @var string
     */
    protected $protocolVersion;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var \Psr\Http\Message\StreamInterface
     */
    protected $body;

    public function withProtocolVersion($version)
    {
        $msg = clone $this;
        $msg->protocolVersion = $version;
        return $msg;
    }


    /**
     * @inheritDoc
     */
    public function hasHeader($name)
    {
        $headers = $this->getHeaders();
        return isset($headers[$name]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name)
    {
        $headers = $this->getHeaders();

        if (isset($headers[$name])) {
            return is_array($headers[$name]) ? $headers[$name] : [$headers[$name]];
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name)
    {
        $headers = $this->getHeader($name);
        return join(',', $headers);
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value)
    {
        $msg = clone $this;
        $ls = $msg->getHeader($name);
        $ls[] = $value;
        $msg->headers[$name] = $ls;
        return $msg;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value)
    {
        $msg = clone $this;
        $msg->getHeaders();
        $msg->headers[$name] = $value;
        return $msg;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader($name)
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }
        $msg = clone $this;
        unset($msg->headers[$name]);
        return $msg;
    }


    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body)
    {
        $msg = clone $this;
        $msg->body = $body;
        return $msg;
    }

}
