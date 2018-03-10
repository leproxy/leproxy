#!/usr/bin/env php
<?php
/**
 * LeProxy is the HTTP/SOCKS proxy server for everybody!
 *
 * LeProxy should be run from the command line. Assuming this file is
 * named `leproxy.php`, try running `$ php leproxy.php --help`.
 *
 * @link https://leproxy.org/ LeProxy project homepage
 * @license https://leproxy.org/#license MIT license
 * @copyright 2017 Christian Lück
 * @version dev
 */

namespace LeProxy\LeProxy;

use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;
use Clue\Commander\Tokens\Tokenizer;
use React\EventLoop\Factory;
use React\Dns\Config\HostsFile;

if (PHP_VERSION_ID < 50400 || PHP_SAPI !== 'cli') {
    echo 'LeProxy HTTP/SOCKS proxy requires running ' . (PHP_SAPI !== 'cli' ? ('via command line (not ' . PHP_SAPI . ')') : ('on PHP 5.4+ (is ' . PHP_VERSION . ')')) . PHP_EOL;
    exit(1);
}

// get current version from git or default to "unknown" otherwise
// this line will be replaced with the static const in the release file.
define('VERSION', ltrim(exec('git describe --always --dirty 2>/dev/null || echo unknown'), 'v'));

require __DIR__ . '/vendor/autoload.php';

// parse options from command line arguments (argv)
$tokenizer = new Tokenizer();
$tokenizer->addFilter('block', function (&$value) {
    $value = ConnectorFactory::coerceBlockUri($value);
    return true;
});
$tokenizer->addFilter('proxy', function (&$value) {
    $value = ConnectorFactory::coerceProxyUri($value);
    return true;
});
$tokenizer->addFilter('hosts', function (&$value) {
    $value = HostsFile::loadFromPathBlocking($value)->getHostsForIp('0.0.0.0');
    return true;
});
$commander = new Router($tokenizer);
$commander->add('--version', function () {
    exit('LeProxy development version ' . VERSION . PHP_EOL);
});
$commander->add('-h | --help', function () {
    exit('LeProxy HTTP/SOCKS proxy

Usage:
    $ php leproxy.php [<listenAddress>] [--allow-unprotected] [--block=<destination>...] [--block-hosts=<path>...] [--proxy=<upstreamProxy>...] [--no-log]
    $ php leproxy.php --version
    $ php leproxy.php --help

Arguments:
    <listenAddress>
        The socket address to listen on.
        The address consists of a full URI which may contain a username and
        password, host and port (or Unix domain socket path).
        By default, LeProxy will listen on the public address 0.0.0.0:8080.
        LeProxy will report an error if it fails to listen on the given address,
        you may try another address or use port `0` to pick a random free port.
        If this address does not contain a username and password, LeProxy will
        run in protected mode and only forward requests from the local host,
        see also `--allow-unprotected`.

    --allow-unprotected
        If no username and password has been given, then LeProxy runs in
        protected mode by default, so that it only forwards requests from the
        local host and can not be abused as an open proxy.
        If you have ensured only legit users can access your system, you can
        pass the `--allow-unprotected` flag to forward requests from all hosts.
        This option should be used with care, you have been warned.

    --block=<destination>
        Blocks forwarding connections to the given destination address.
        Any number of destination addresses can be given.
        Each destination address can be in the form `host` or `host:port` and
        `host` may contain the `*` wildcard to match anything.
        Subdomains for each host will automatically be blocked.

    --block-hosts=<path>
        Loads the hosts file from the given file path and blocks all of the
        hostnames (and subdomains) that match the IP `0.0.0.0`.
        Any number of hosts files can be given, all hosts will be blocked.

    --proxy=<upstreamProxy>
        An upstream proxy server where each connection request will be
        forwarded to (proxy chaining).
        Any number of upstream proxies can be given.
        Each address consists of full URI which may contain a scheme, username
        and password, host and port. Default scheme is `http://`, default port
        is `8080` for all schemes.

    --no-log
        By default, LeProxy logs all connection attempts to STDOUT for
        debugging purposes. This can be avoided by passing this argument.

    --version
        Prints the current version of LeProxy and exits.

    --help, -h
        Shows this help and exits.

Examples:
    $ php leproxy.php
        Runs LeProxy on public default address 0.0.0.0:8080 (protected mode)

    $ php leproy.php 127.0.0.1:1080
        Runs LeProxy on custom address 127.0.0.1:1080 (protected mode, local only)

    $ php leproxy.php user:pass@0.0.0.0:8080
        Runs LeProxy on public default addresses and require authentication

    $ php leproxy.php --block=youtube.com --block=*:80
        Runs LeProxy on default address and blocks access to youtube.com and
        port 80 on all hosts (standard plaintext HTTP port).

    $ php leproxy.php --proxy=http://user:pass@127.1.1.1:8080
        Runs LeProxy so that all connection requests will be forwarded through
        an upstream proxy server that requires authentication.
');
});
$commander->add('[--allow-unprotected] [--block=<block:block>...] [--block-hosts=<file:hosts>...] [--proxy=<proxy:proxy>...] [--no-log] [<listen>]', function ($args) {
    // validate listening URI or assume default URI
    $args['listen'] = ConnectorFactory::coerceListenUri(isset($args['listen']) ? $args['listen'] : '');

    $args['allow-unprotected'] = isset($args['allow-unprotected']);
    if ($args['allow-unprotected'] && strpos($args['listen'], '@') !== false) {
        throw new \InvalidArgumentException('Unprotected mode can not be used with authentication required');
    }

    if (isset($args['block-hosts'])) {
        if (!isset($args['block'])) {
            $args['block'] = array();
        }
        foreach ($args['block-hosts'] as $hosts) {
            $args['block'] += $hosts;
        }
    }

    // filter duplicate block entries and subdomains
    if (isset($args['block'])) {
        $args['block'] = ConnectorFactory::filterRootDomains($args['block']);
    }

    return $args;
});
try {
    $args = $commander->handleArgv();
} catch (\Exception $e) {
    $message = '';
    if (!$e instanceof NoRouteFoundException) {
        $message = ' (' . $e->getMessage() . ')';
    }

    fwrite(STDERR, 'Usage Error: Invalid command arguments given, see --help' . $message . PHP_EOL);

    // sysexits.h: #define EX_USAGE 64 /* command line usage error */
    exit(64);
}

$loop = Factory::create();

// set next proxy server chain -> p1 -> p2 -> p3 -> destination
$connector = ConnectorFactory::createConnectorChain(isset($args['proxy']) ? $args['proxy'] : array(), $loop);

if (isset($args['block'])) {
    $connector = ConnectorFactory::createBlockingConnector($args['block'], $connector);
}

// log all connection attempts to STDOUT (unless `--no-log` has been given)
if (!isset($args['no-log'])) {
    $connector = new LoggingConnector($connector, new Logger());
}

// create proxy server and start listening on given address
$proxy = new LeProxyServer($loop, $connector);
try {
    $socket = $proxy->listen($args['listen'], $args['allow-unprotected']);
} catch (\RuntimeException $e) {
    fwrite(STDERR, 'Program error: Unable to start listening, maybe try another port? (' . $e->getMessage() . ')'. PHP_EOL);

    // sysexits.h: #define EX_OSERR 71 /* system error (e.g., can't fork) */
    exit(71);
}

$addr = str_replace(array('tcp://', 'unix://'), array('http://', 'http+unix://'), $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . ' (';
if (strpos($args['listen'], '@') !== false) {
    echo 'authentication required';
} elseif ($args['allow-unprotected']) {
    echo 'unprotected mode, open proxy';
} else {
    echo 'protected mode, local access only';
}
echo ')' . PHP_EOL;

if (isset($args['proxy'])) {
    echo 'Forwarding via: ' . implode(' -> ', $args['proxy']) . PHP_EOL;
}

if (isset($args['block'])) {
    echo 'Blocking a total of ' . count($args['block']) . ' destination(s)' . PHP_EOL;
}

$loop->run();
