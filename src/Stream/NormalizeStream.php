<?php

namespace Wind\Web\Stream;

trait NormalizeStream
{

    public function __toString()
    {
        return $this->getContents();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function detach()
    {}

    /**
     * Always return null to indicate the size is unknown,
     * so that HttpServer will send it as a streamed response.
     */
    public function getSize()
    {
        return null;
    }

    public function tell()
    {
        return 0;
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = \SEEK_SET): void
    {}

    public function rewind()
    {}

    public function getMetadata($key = null)
    {}

}
