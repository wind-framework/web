<?php

namespace Wind\Web\Exception;

use Throwable;

class HttpException extends \Exception
{

    public function __construct($statusCode=200, $message='', Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

}