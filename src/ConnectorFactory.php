<?php

namespace LeProxy\LeProxy;

use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;

class ConnectorFactory
{
    /**
     * Creates a new connector for the given proxy chain (list of proxy servers)
     *
     * The proxy chain may contain any number of proxy server URIs.
     * Each proxy server URI may use `http://` or `socks[5|4a|4]://` URI scheme,
     * with `http://` being the default if none is given.
     *
     * The proxy chain may be empty, in which case the connection will be direct.
     *
     * @param string[] $path URIs
     * @param LoopInterface $loop
     * @return \React\Socket\ConnectorInterface
     * @throws \InvalidArgumentException if either proxy server URI is invalid
     */
    public static function createConnectorChain(array $path, LoopInterface $loop)
    {
        $connector = new Connector($loop);

        foreach ($path as $proxy) {
            if (strpos($proxy, '://') === false || strpos($proxy, 'http://') === 0) {
                $connector = new HttpClient($proxy, $connector);
            } else {
                $connector = new SocksClient($proxy, $connector);
            }
        }

        return $connector;
    }
}
