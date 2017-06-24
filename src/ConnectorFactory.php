<?php

namespace LeProxy\LeProxy;

use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use ConnectionManager\Extra\ConnectionManagerReject;
use ConnectionManager\Extra\Multiple\ConnectionManagerSelective;

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

    /**
     * Creates a new connector that only blocks all hosts from the given block list
     *
     * The block list may contain any number of host entries in the form `host`
     * or `host:port` and may contain `*` wildcard to match anything.
     *
     * Any host that is not on the block list will be forwarded through the base
     * connector given as the second argument.
     *
     * @param string[] $block
     * @param ConnectorInterface $base
     * @return ConnectorInterface
     */
    public static function createBlockingConnector(array $block, ConnectorInterface $base)
    {
        $reject = new ConnectionManagerReject();

        // reject all hosts given in the block list
        $filter = array();
        foreach ($block as $host) {
            $filter[$host] = $reject;

            // also reject all subdomains (*.domain), unless this already matches
            if (substr($host, 0, 1) !== '*') {
                $filter['*.' . $host] = $reject;
            }
        }

        // this is a blacklist, so allow all other hosts by default
        if (!isset($filter['*'])) {
            $filter['*'] = $base;
        }

        return new ConnectionManagerSelective($filter);
    }
}
