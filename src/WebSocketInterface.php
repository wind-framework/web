<?php

namespace Wind\Web;

/**
 * WebSocket Controller Interfact
 */
interface WebSocketInterface
{

    public function onConnect($connection, $vars);

    public function onMessage($connection, $data);

    public function onClose($connection);

}
