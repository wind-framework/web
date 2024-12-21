<?php

namespace Wind\Web\Stream;

use Amp\ByteStream\Pipe;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Psr\Http\Message\StreamInterface;
use Revolt\EventLoop;

use function Amp\ByteStream\buffer;

/**
 * Byte Stream
 *
 * Example:
 * ```php
 *
 * $stream = new ByteStream();
 *
 * async(function() use ($stream) {
 *     try {
 *         foreach (range(0, 100) as $num) {
 *             $stream->write((string)$num."\r\n");
 *             delay(0.1);
 *         }
 *     } finally {
 *         $stream->end();
 *     }}
 * });
 *
 * return new Response(200, $stream);
 * ```
 */
final class ByteStream implements StreamInterface, StreamingInterface
{

    use NormalizeStream;

    private ReadableStream $source;
    private WritableStream $sink;
    private string $ref = '';

    /**
     * @param int $bufferSize
     * @param float $idleTimeout Force end stream after ByteStream idle (no write, no read) seconds, 0 means no timeout
     */
    public function __construct(int $bufferSize=4096, private float $idleTimeout=30)
    {
        $pipe = new Pipe($bufferSize);
        $this->source = $pipe->getSource();
        $this->sink = $pipe->getSink();

        if ($idleTimeout > 0) {
            $this->ref = EventLoop::delay($idleTimeout, fn() => $this->end());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function end()
    {
        $this->ref && EventLoop::cancel($this->ref);
        $this->sink->end();
    }

    public function close()
    {
        $this->sink->close();
        $this->source->close();
    }

    public function eof()
    {
        return $this->source->isReadable();
    }

    public function isWritable()
    {
        return $this->sink->isWritable();
    }

    public function write($string): int
    {
        $this->sink->write($string);
        $this->activate();
        return strlen($string);
    }

    public function isReadable()
    {
        return $this->source->isReadable();
    }

    public function read($length=-1)
    {
        $this->activate();
        return $this->source->read();
    }

    public function getContents()
    {
        return buffer($this->source);
    }

    private function activate()
    {
        if ($this->ref) {
            EventLoop::cancel($this->ref);
            $this->ref = EventLoop::delay($this->idleTimeout, fn() => $this->end());
        }
    }

}
