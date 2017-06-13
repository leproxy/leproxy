<?php

namespace LeProxy\LeProxy;

use React\Socket\Connector;
use React\Promise\Promise;
use React\Stream\WritableStreamInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ConnectionInterface;
use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;

/** @internal */
class LoggingConnector implements ConnectorInterface
{
    private $connector;
    private $outputStream;
    private $protocol = 'tcp';
    private $auth = null;

    public function __construct(ConnectorInterface $connector, WritableStreamInterface $outputStream)
    {
        if ($connector instanceof HttpClient) {
            $this->protocol = 'http';
        }

        if ($connector instanceof SocksClient) {
            $this->protocol = 'socks5';
        }

        $this->connector = $connector;
        $this->outputStream = $outputStream;
    }

    public function connect($uri)
    {
        $that = $this;
        $connector = $this->connector;
        $auth = $this->auth;

        return new Promise(
            function ($resolve, $reject) use ($connector, $uri, $that, $auth) {
                $connector->connect($uri)->then(
                    function (ConnectionInterface $remote) use ($that, $uri, $resolve, $auth) {
                        $address = str_replace('tcp://', $this->protocol . '://', $remote->getLocalAddress());
                        $message = 'connected ' . $address . ' to tcp://' . $uri;
                        if ($auth !== null) {
                            foreach ($auth as $user => $key) {
                                $message = 'connected ' . $user . '@' . $address . ' to tcp://' . $uri;
                            }
                        }

                        $that->log($message);
                        $resolve($remote);
                    }
                );
            }
        );
    }

    public function log($message)
    {
        $this->outputStream->write(date('Y-m-d H:i:s') . ' ' . $message. PHP_EOL);
    }

    public function setAuth($auth)
    {
        $this->auth = $auth;
    }
}
