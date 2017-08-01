<?php

namespace LeProxy\LeProxy;

use React\Socket\ConnectorInterface;

/**
 * Connector decorator to simply append the given source address as an URI query parameter
 */
class SourceConnector implements ConnectorInterface
{
    private $connector;
    private $source;

    public function __construct(ConnectorInterface $connector, $source)
    {
        $this->connector = $connector;
        $this->source = $source;
    }

    public function connect($uri)
    {
        $uri .= (strpos($uri, '?') === false ? '?' : '&') . 'source=' . rawurlencode($this->source);

        return $this->connector->connect($uri);
    }
}
