<?php

namespace LeProxy\LeProxy;

use Clue\React\Socks\Server as SocksServer;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Socket\Server as Socket;
use InvalidArgumentException;

/**
 * Integrates HTTP and SOCKS proxy servers into a single server instance
 */
class LeProxyServer
{
    private $connector;
    private $loop;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;
        $this->loop = $loop;
    }

    /**
     * @param string $listen
     * @return \React\Socket\ServerInterface
     * @throws \InvalidArgumentException
     */
    public function listen($listen)
    {
        // prepend default scheme
        $pos = strpos($listen, '://');
        if ($pos === false) {
            $listen = 'http://' . $listen;
        }

        $parts = parse_url($listen);
        if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['port'])) {
            throw new InvalidArgumentException('Invalid URI for listening address');
        }

        $socket = new Socket($parts['host'] . ':' . $parts['port'], $this->loop);

        // start new proxy server which uses the above connector for forwarding
        $unification = new ProtocolDetector($socket);
        $http = new HttpProxyServer($this->loop, $unification->http, $this->connector);
        $socks = new SocksServer($this->loop, $unification->socks, $this->connector);

        // require authentication if listening URI contains username/password
        if (isset($parts['user']) || isset($parts['pass'])) {
            $auth = array(
                rawurldecode($parts['user']) => isset($parts['pass']) ? rawurldecode($parts['pass']) : ''
            );

            $http->setAuthArray($auth);
            $socks->setAuthArray($auth);
        }

        return $socket;
    }
}
