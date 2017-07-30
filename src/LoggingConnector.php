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
    private $logger;

    public function __construct(ConnectorInterface $connector, Logger $logger)
    {
        $this->connector = $connector;
        $this->logger = $logger;
    }

    public function connect($uri)
    {
        // parse source address from URI ?source=X parameter
        $parts = parse_url(((strpos($uri, '://') === false) ? 'tcp://' : '') . $uri);
        $args = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $args);
        }
        $source = isset($args['source']) ? $args['source'] : null;

        return $this->connector->connect($uri)->then(
            function (ConnectionInterface $connection) use ($source, $uri) {
                $this->logger->logConnection($source, $uri, $connection->getRemoteAddress());

                return $connection;
            },
            function (\Exception $e) use ($source, $uri) {
                $this->logger->logFailConnection($source, $uri, $e->getMessage());

                throw $e;
            }
        );
    }
}
