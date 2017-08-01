<?php

namespace LeProxy\LeProxy;

/**
 * Domain specific logger, formats and writes out log messages to STDOUT
 */
class Logger
{
    /**
     * Logs a successful connection attempt
     *
     * @param ?string $source      client source address
     * @param ?string $destination target destination host or IP
     * @param ?string $remote      actual remote address may differ from destination address (if known)
     * @return void
     */
    public function logConnection($source, $destination, $remote)
    {
        $destination = $this->destination($destination);

        // append actual remote address (IP) to destination if not the same
        if ($remote !== null) {
            // only consider host and port from remote address
            $remote = $this->destination($remote);

            // append actual remote address (IP) only if not the same as destination
            if ($remote !== $destination) {
                $destination .= ' (' . $remote . ')';
            }
        }

        $this->log($this->source($source) . ' connected to ' . $destination);
    }

    /**
     * Logs an unsuccessful connection attempt
     *
     * @param ?string $source      client source destination
     * @param ?string $destination target destination host or IP
     * @param ?string $reason      rejection/fail reason
     * @return void
     */
    public function logFailConnection($source, $destination, $reason)
    {
        $this->log($this->source($source) . ' failed to connect to ' . $this->destination($destination) . ' (' . $reason . ')');
    }

    /**
     * Formats the given sources address for logging as `scheme://[user@]ip`
     *
     * Removes password and port etc. from URI.
     *
     * @param ?string $source
     * @return string
     */
    private function source($source)
    {
        $parts = parse_url($source);
        if (isset($parts['scheme'], $parts['host'])) {
            $source = $parts['scheme'] . '://';
            if (isset($parts['user'])) {
                $source .= $parts['user'] . '@';
            }
            $source .= $parts['host'];
        } else {
            $source = '???';
        }

        return $source;
    }

    /**
     * Formats the given destination address for logging as `ip:port`
     *
     * Removes scheme and query parameter etc. from URI.
     *
     * @param string $destination
     * @return string
     */
    private function destination($destination)
    {
        $parts = parse_url((strpos($destination, '://') === false ? 'tcp://' : '') . $destination);
        if ($parts && isset($parts['host'], $parts['port'])) {
            $destination = $parts['host'] . ':' . $parts['port'];
        }

        return $destination;
    }

    /**
     * Writes the given log message to STDOUT with the current datetime
     *
     * @param string $message
     * @return void
     */
    private function log($message)
    {
        $time = explode(' ', microtime(false));
        echo date('Y-m-d H:i:s.', $time[1]) . sprintf('%03d ', $time[0] * 1000) . $message . PHP_EOL;
    }
}
