<?php

namespace Wind\Web;

use Psr\Http\Message\ServerRequestInterface;

interface RequestInterface extends ServerRequestInterface
{

    public function get($key, $defaultValue=null);

    public function post($key, $defaultValue=null);

    public function cookie($key, $defaultValue=null);

    public function file($key);

}
