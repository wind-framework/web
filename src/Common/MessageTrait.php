<?php

namespace Wind\Web\Common;

use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 Http Message Trait
 */
trait MessageTrait
{

    /**
     * @var string
     */
    protected $protocolVersion;

    /**
     * @var array
     */
    protected $headers = [];
    private $headerNames = [];

    /**
     * @var \Psr\Http\Message\StreamInterface
     */
    protected $body;

    protected function initHeaders($headers)
    {
        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $this->headers[$key] = $value;
            } else {
                $this->headers[$key] = [$value];
            }
            $this->headerNames[$this->normalize($key)] = $key;
        }
    }

    private function normalize($name)
    {
        return \strtr($name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    public function withProtocolVersion($version)
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $msg = clone $this;
        $msg->protocolVersion = $version;
        return $msg;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader($name)
    {
        return isset($this->headerNames[$this->normalize($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name)
    {
        $name = $this->normalize($name);
        return isset($this->headerNames[$name]) ? $this->headers[$this->headerNames[$name]] : [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name)
    {
        $headers = $this->getHeader($name);
        return join(', ', $headers);
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value)
    {
        $normalized = $this->normalize($name);
        $msg = clone $this;

        if (isset($msg->headerNames[$normalized])) {
            unset($msg->headers[$msg->headerNames[$normalized]]);
        }

        $msg->headerNames[$normalized] = $name;
        $msg->headers[$name] = is_array($value) ? $value : [$value];

        return $msg;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value)
    {
        $normalized = $this->normalize($name);
        $msg = clone $this;

        if (!isset($msg->headerNames[$normalized])) {
            $msg->headerNames[$normalized] = $name;
            $msg->headers[$name] = [];
        } else {
            $name = $msg->headerNames[$normalized];
        }

        if (is_array($value)) {
            $msg->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $msg->headers[$name][] = $value;
        }
        
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

        $normalized = $this->normalize($name);
        $msg = clone $this;
        
        unset($msg->headers[$this->headerNames[$normalized]], $msg->headerNames[$normalized]);

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
