#!/usr/bin/env php
<?php

namespace LeProxy\LeProxy;

use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;
use React\EventLoop\Factory;
use React\Stream\WritableResourceStream;

if (PHP_VERSION_ID < 50400 || PHP_SAPI !== 'cli') {
    echo 'LeProxy HTTP/SOCKS proxy requires running ' . (PHP_SAPI !== 'cli' ? ('via command line (not ' . PHP_SAPI . ')') : (' on PHP 5.4+ (is ' . PHP_VERSION . ')')) . PHP_EOL;
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

// parse options from command line arguments (argv)
$commander = new Router();
$commander->add('-h | --help', function () {
    exit('LeProxy HTTP/SOCKS proxy

Usage:
    $ php leproxy.php [<listenAddress> [<upstreamProxy>...]]
    $ php leproxy.php --help

Arguments:
    <listenAddress>
        The socket address to listen on.
        The address consists of a full URI which may contain a username and
        password, host and port.
        By default, LeProxy will listen on the address 127.0.0.1:1080.

    <upstreamProxy>
        An upstream proxy servers where each connection request will be
        forwarded to (proxy chaining).
        Any number of upstream proxies can be given.
        Each address consists of full URI which may contain a scheme, username
        and password, host and port. Default scheme is `http://`.

    --help, -h
        shows this help and exits

    --log, l
        shows live log on the console, not persistent

Examples:
    $ php leproxy.php
        Runs LeProxy on default address 127.0.0.1:1080 (local only)

    $ php leproxy.php user:pass@0.0.0.0:1080
        Runs LeProxy on all addresses (public) and require authentication

    $ php leproxy.php 127.0.0.1:1080 http://user:pass@127.1.1.1:1080
        Runs LeProxy locally without authentication and forwards all connection
        requests through an upstream proxy that requires authentication.
');
});
$commander->add('[-l | --log] [<listen> [<path>...]]', function ($args) {
    $activeLog = false;
    if (array_key_exists('log', $args) || array_key_exists('l', $args)) {
        $activeLog = true;
    }
    $args['log'] = $activeLog;


    return $args + array(
        'listen' => '127.0.0.1:1080',
        'path' => array(),
    );
});

try {
    $args = $commander->handleArgv();
} catch (NoRouteFoundException $e) {
    fwrite(STDERR, 'Usage Error: Invalid command arguments given, see --help' . PHP_EOL);

    // sysexits.h: #define EX_USAGE 64 /* command line usage error */
    exit(64);
}

$loop = Factory::create();

// set next proxy server chain -> p1 -> p2 -> p3 -> destination
$connector = ConnectorFactory::createConnectorChain($args['path'], $loop);
if ($args['log']) {
    $connector = new LoggingConnector($connector, new WritableResourceStream(STDOUT, $loop));
}

// listen on 127.0.0.1:1080 or first argument
$proxy = new LeProxyServer($loop, $connector);
$socket = $proxy->listen($args['listen']);

$addr = str_replace('tcp://', 'http://', $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . PHP_EOL;

if ($args['path']) {
    echo 'Forwarding via: ' . implode(' -> ', $args['path']) . PHP_EOL;
}

$loop->run();
