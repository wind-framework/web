<?php

namespace Wind\Web;

use Wind\Web\Exception\RequestUnsupportedException;
use Workerman\Connection\TcpConnection;

/**
 * Wind Uri Adapted with Workerman Request
 * @package Wind\Web
 */
class Uri implements \Psr\Http\Message\UriInterface
{

    /**
     * @var \Workerman\Protocols\Http\Request
     */
    protected $request;

    /**
     * @var TcpConnection
     */
    protected $connection;

    protected $scheme;
    protected $host;
    protected $port;
    protected $path;
    protected $query;
    protected $fragment;

    public function __construct(\Workerman\Protocols\Http\Request $request, TcpConnection $connection)
    {
        $this->request = $request;
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    public function getScheme()
    {
        return $this->scheme ?: ($this->connection->transport == 'ssl' ? 'https' : 'http');
    }

    /**
     * @inheritDoc
     */
    public function getAuthority()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getHost()
    {
        return $this->host ?: $this->request->host(true);
    }

    /**
     * @inheritDoc
     */
    public function getPort()
    {
        if (!$this->port) {
            $host = $this->request->host();
            $pos = strpos($host, ':');

            if ($pos !== false) {
                $this->port = substr($host, $pos+1);
            }
        }

        return $this->port;
    }

    /**
     * @inheritDoc
     */
    public function getPath()
    {
        return $this->path ?: $this->request->path();
    }

    /**
     * @inheritDoc
     */
    public function getQuery()
    {
        return $this->query ?: $this->request->queryString();
    }

    /**
     * @inheritDoc
     */
    public function getFragment()
    {
        $path = $this->request->path();
        $fragment = strstr($path, '#');
        if ($fragment !== false) {
            return substr($fragment, 1);
        } else {
            return '';
        }
    }

    /**
     * @inheritDoc
     */
    public function withScheme($scheme)
    {
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            throw new \InvalidArgumentException("Invalid scheme '$scheme'.");
        }

        $uri = clone $this;
        $uri->scheme = $scheme;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo($user, $password = null)
    {
        throw new RequestUnsupportedException('Unsupported method \''.__METHOD__.'\' for Uri.');
    }

    /**
     * @inheritDoc
     */
    public function withHost($host)
    {
        if (!preg_match('/^[\w\d\.\-]+$/i', $host)) {
            throw new \InvalidArgumentException("Invalid hostname '$host'.");
        }

        $uri = clone $this;
        $uri->host = $host;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function withPort($port)
    {
        $uri = clone $this;
        $uri->port = $port;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function withPath($path)
    {
        $uri = clone $this;
        $uri->path = $path;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function withQuery($query)
    {
        $uri = clone $this;
        $uri->query = $query;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function withFragment($fragment)
    {
        $uri = clone $this;
        $uri->fragment = $fragment;
        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {

        $url = $this->getScheme().'://'.$this->getHost();

        $port = $this->getPort();
        if ($port) {
            $url .= ':'.$port;
        }

        $url .= $this->getPath();

        $query = $this->getQuery();
        if ($query) {
            $url .= '?'.$query;
        }

        return $url;
    }
}