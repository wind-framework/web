<?php

namespace Wind\Web\Exception;

use Throwable;

class HttpException extends \Exception
{

    public function __construct($statusCode=400, $message='', Throwable $previous = null, public readonly array $headers=[])
    {
        parent::__construct($message, $statusCode, $previous);
    }

}
