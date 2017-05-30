<?php

use React\Socket\ServerInterface;
use React\Socket\ConnectionInterface;

/**
 * Detects HTTP and SOCKS protocol unification on a single listening socket
 *
 * This implements a very naive approach where it waits for the first data chunk
 * received for each connection.
 *
 * Everything that starts with a 0x05 or 0x04 byte is expected to be a SOCKS5 or
 * SOCKS4 connection respectively, everything else is assumed to be HTTP.
 *
 * @uses NullServer
 */
class ProtocolDetector
{
    public $http;
    public $socks;

    private $server;

    public function __construct(ServerInterface $server)
    {
        $this->server = $server;
        $this->server->on('connection', array($this, 'handleConnection'));

        $this->http = new NullServer();
        $this->socks = new NullServer();

        $http = $this->http;
        $this->server->on('error', function (Exception $e) use ($http) {
            $http->emit('error', array($e));
        });
    }

    /** @internal */
    public function handleConnection(ConnectionInterface $connection)
    {
        $that = $this;
        $connection->once('data', function ($chunk) use ($connection, $that) {
            if (isset($chunk[0]) && ($chunk[0] === "\x05" || $chunk[0] === "\x04")) {
                $that->socks->emit('connection', array($connection));
            } else {
                $that->http->emit('connection', array($connection));
            }

            $connection->emit('data', array($chunk));
        });
    }
}
