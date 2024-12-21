<?php

namespace Wind\Web;

/**
 * Buffer to prevent workerman http encoding.
 */
class Buffer
{

    public function __construct(private string $content)
    {}

    public function __toString()
    {
        return $this->content;
    }

}
