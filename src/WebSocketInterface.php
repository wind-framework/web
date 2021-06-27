<?php

namespace Wind\Web;

use Workerman\Connection\TcpConnection;

/**
 * WebSocket Controller Interface
 */
interface WebSocketInterface
{

    /**
     * Websocket connection coming
     *
     * @param TcpConnection $connection
     * @param array $vars
     * @return void
     */
    public function onConnect($connection, $vars);

    /**
     * Received a websocket message
     *
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    public function onMessage($connection, $data);

    /**
     * Websocket connection closed
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose($connection);

}
