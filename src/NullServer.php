<?php

namespace LeProxy\LeProxy;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;

/**
 * Pseudo server used for HTTP/SOCKS unification
 *
 * There's actually only a single server socket below, but both higher level
 * parsers require a dedicated server socket, which this abstraction provides.
 */
class NullServer extends EventEmitter implements ServerInterface
{
    public function getAddress()
    {
        return null;
    }

    public function pause()
    {
        // NO-OP
    }

    public function resume()
    {
        // NO-OP
    }

    public function close()
    {
        // NO-OP
    }
}
