<?php

namespace LeProxy\LeProxy;

use React\Socket\ConnectorInterface;
use React\Socket\ConnectionInterface;

/**
 * Connector decorator which logs the connection attempt
 *
 * Parses the source and destination address from the URI for each connection
 * attempt, forwards the URI to the actual connector and writes a log to STDOUT.
 */
class LoggingConnector implements ConnectorInterface
{
    private $connector;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    public function connect($uri)
    {
        // format destination as `host:port` (remove scheme and any arguments)
        $parts = parse_url(((strpos($uri, '://') === false) ? 'tcp://' : '') . $uri);
        $destination = isset($parts['host'], $parts['port']) ? ($parts['host'] . ':' . $parts['port']) : '???';

        $args = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $args);
        }

        // format source as `scheme://[user@]ip` (remove password and port)
        $parts = isset($args['source']) ? parse_url($args['source']) : array();
        if (isset($parts['scheme'], $parts['host'])) {
            $source = $parts['scheme'] . '://';
            if (isset($parts['user'])) {
                $source .= $parts['user'] . '@';
            }
            $source .= $parts['host'];
        } else {
            $source = '???';
        }

        return $this->connector->connect($uri)->then(
            function (ConnectionInterface $connection) use ($source, $destination) {
                // append actual remote address (IP) to destination if not the same
                $remote = $connection->getRemoteAddress();
                if ($remote !== null) {
                    // only consider host and port from remote address
                    $parts = parse_url((strpos($remote, '://') === false ? 'tcp://' : '') . $remote);
                    if ($parts && isset($parts['host'], $parts['port'])) {
                        $remote = $parts['host'] . ':' . $parts['port'];
                    }

                    // append actual remote address (IP) only if not the same as destination
                    if ($remote !== $destination) {
                        $destination .= ' (' . $remote . ')';
                    }
                }

                $this->log($source . ' connected to ' . $destination);

                return $connection;
            },
            function (\Exception $e) use ($source, $destination) {
                $this->log($source . ' failed to connect to ' . $destination . ' (' . $e->getMessage() . ')');

                throw $e;
            }
        );
    }

    private function log($message)
    {
        $time = explode(' ', microtime(false));
        echo date('Y-m-d H:i:s.', $time[1]) . sprintf('%03d ', $time[0] * 1000) . $message . PHP_EOL;
    }
}
