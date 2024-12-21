<?php

namespace Wind\Web;

use Wind\Web\Stream\ByteStream;

/**
 * Server Sent Events
 *
 * Example:
 * ```
 * $response = new ServerSentEvents();
 *
 * async(function() use ($response) {
 *    foreach (range(0, 100) as $num) {
 *        $response->send((string)$num);
 *        delay(0.1);
 *    }
 * });
 *
 * return $response;
 * ```
 */
class ServerSentEventsResponse extends Response
{

    private $stream;

    public function __construct($statusCode=200, $headers=[])
    {
        $this->stream = new ByteStream();
        $headers['Content-Type'] = 'text/event-stream';
        parent::__construct($statusCode, $this->stream, $headers);
    }

    public function end()
    {
        $this->stream->end();
    }

    /**
     * Send Message
     *
     * @param string $data
     * @param string|null $event
     * @param string|null $id
     * @param string|null $retry
     * @return void
     */
    public function send($data, $event=null, $id=null, $retry=null)
    {
        $content = '';
        $event !== null && $content .= $this->format('event', $event);
        $id !== null && $content .= $this->format('id', $id);
        $content .= $this->format('data', $data);
        $retry !== null && $content .= $this->format('retry', $retry);
        $this->stream->write($content."\n");
    }

    /**
     * Send Comment
     *
     * @param string $string
     * @return void
     */
    public function comment($string)
    {
        $this->stream->write($this->format('', $string)."\n");
    }

    private function format($field, $string)
    {
        if (str_contains($string, "\n")) {
            $content = '';
            foreach (explode("\n", $string) as $row) {
                $content .= "$field: $row\n";
            }
            return $content;
        } else {
            return "$field: $string\n";
        }
    }

}
