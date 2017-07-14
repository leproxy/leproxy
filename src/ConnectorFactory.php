<?php

namespace LeProxy\LeProxy;

use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;

class ConnectorFactory
{
    /**
     * Parses the given proxy URI and adds default scheme and port or throws on error
     *
     * @param string $uri
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function coerceProxyUri($uri)
    {
        if ($uri === '') {
            throw new \InvalidArgumentException('Upstream proxy URI must not be empty');
        }
        if (strpos($uri, '://') === false) {
            $uri = 'http://' . $uri;
        }

        $parts = parse_url($uri);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Upstream proxy "' . $uri . '" can not be parsed as a valid URI');
        }

        if (!in_array($parts['scheme'], array('http', 'socks', 'socks5', 'socks4', 'socks4a'))) {
            throw new \InvalidArgumentException('Upstream proxy scheme "' . $parts['scheme'] . '://" not supported');
        }

        // always assume default port 8080 irrespective of protocol
        if (!isset($parts['port'])) {
            $parts['port'] = 8080;
        }

        // prepend user/pass if either is given
        if (isset($parts['user']) || isset($parts['pass'])) {
            // explicitly replace socks:// with socks5://
            if ($parts['scheme'] === 'socks') {
                $parts['scheme'] = 'socks5';
            }

            // only http:// and socks5:// support authentication
            if ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'socks5') {
                throw new \InvalidArgumentException('Upstream proxy scheme "' . $parts['scheme'] . '://" does not support username/password authentication');
            }

            $parts += array('user' => '', 'pass' => '');
            $parts['host'] = $parts['user'] . ':' . $parts['pass'] . '@' . $parts['host'];
        }

        return $parts['scheme'] . '://' . $parts['host'] . ':' . $parts['port'];
    }
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
