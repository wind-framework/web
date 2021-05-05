<?php

namespace Wind\Web;

abstract class Controller
{

    protected $middlewares = [];

    /**
     * @return void|\Amp\Promise|\Generator
     */
    public function init()
    {}

    public function middlewares()
    {
        return [];
    }

}