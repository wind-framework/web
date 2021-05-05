<?php

namespace Wind\Web;

use Psr\Http\Message\StreamInterface;

class StreamBody implements StreamInterface
{

    /**
     * RawBody
     *
     * @var string
     */
    public $rawBody;

    public function __construct($rawBody)
    {
        $this->rawBody = $rawBody;
    }

    public function __toString() {
        return $this->rawBody;
    }

    public function close() { }

    public function detach() { }

    public function getSize() { }

    public function tell() { }

    public function eof() { }

    public function isSeekable() { }

    public function seek($offset, $whence = SEEK_SET) { }

    public function rewind() { }

    public function isWritable() { }

    public function write($string) { }

    public function isReadable() { }

    public function read($length) { }

    public function getContents() { }

    public function getMetadata($key = null) { }
    
}
