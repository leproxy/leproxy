<?php

namespace LeProxy\LeProxy;

use React\Socket\ConnectorInterface;
use React\Promise\Timer\TimeoutException;

/**
 * Wraps an existing connector so that its socket error codes will ignored for upstream errors
 *
 * Scenario: LeProxy uses proxy chaining and one of the upstream proxies in the
 * chain returns an authentication or timeout error. We do not want to indicate
 * this exact error to the client because he's not in charge of fixing this
 * either. Direct error codes will be passed through though.
 */
class SocksErrorConnector implements ConnectorInterface
{
    private $connector;
    private $localOnly;

    public function __construct(ConnectorInterface $connector, $localOnly)
    {
        $this->connector = $connector;
        $this->localOnly = $localOnly;
    }

    public function connect($uri)
    {
        // If we are running in protected mode and the server does not have any authentication method set,
        // then we verify the connection attempt is coming from a local address or reject right away.
        // The server always includes the client address as a "source" query parameter, so we can parse its IP.
        $args = array();
        \parse_str(\parse_url($uri, \PHP_URL_QUERY), $args);
        if ($this->localOnly && isset($args['source'])) {
            $remote = \trim(\parse_url($args['source'], \PHP_URL_HOST), '[]');

            if (!ConnectorFactory::isIpLocal($remote)) {
                return \React\Promise\reject(new \RuntimeException(
                    'Remote access denied in protected mode (EACCES)',
                    \defined('SOCKET_ACCESS') ? \SOCKET_EACCES : 13
                ));
            }
        }

        return $this->connector->connect($uri)->then(null, function (\Exception $e) {
            // report (only) explicit block list as ruleset violation
            if ($e->getCode() === ConnectorFactory::CODE_BLOCKED) {
                throw new \RuntimeException($e->getMessage() . ' (EACCES)', defined('SOCKET_ACCESS') ? SOCKET_EACCES : 13);
            }

            // report (only) timeout issues and direct errors from socket connector
            if ($e instanceof TimeoutException || $e->getPrevious() === null) {
                throw $e;
            }

            // otherwise omit any error code and return this as a generic error without a special code
            throw new \RuntimeException($e->getMessage());
        });
    }
}
