<?php

namespace Wind\Web\Stream;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Psr\Http\Message\StreamInterface;

use function Amp\ByteStream\buffer;

/**
 * Iterable Stream
 *
 * Example:
 * ```php
 * $iterable = (function() {
 *    foreach (range(0, 100) as $num) {
 *        yield str_repeat((string)$num)."\r\n";
 *        delay(0.1);
 *    }
 * })();
 *
 * return new Response(200, new IterableStream($iterable));
 * ```
 */
final class IterableStream implements StreamInterface, StreamingInterface
{

    use NormalizeStream;

    private ReadableStream $stream;

    public function __construct(iterable $iterable)
    {
        $this->stream = new ReadableIterableStream($iterable);
    }

    public function close()
    {
        return $this->stream->close();
    }

    public function eof()
    {
        return $this->stream->isReadable();
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string): int
    {
        return 0;
    }

    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    public function read($length=-1)
    {
        return $this->stream->read();
    }

    public function getContents()
    {
        return buffer($this->stream);
    }

}
