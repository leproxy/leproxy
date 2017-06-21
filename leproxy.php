<?php

use Clue\Commander\Router;

require __DIR__ . '/vendor/autoload.php';

// parse options from command line arguments (argv)
$commander = new Router();
$commander->add('[<listen> [<path>...]]', function ($args) {
    return $args + array(
        'listen' => '127.0.0.1:1080',
        'path' => array()
    );
});
$args = $commander->handleArgv();

$loop = React\EventLoop\Factory::create();

// set next proxy server chain -> p1 -> p2 -> p3 -> destination
$connector = ConnectorFactory::createConnectorChain($args['path'], $loop);

// listen on 127.0.0.1:1080 or first argument
$proxy = new LeProxyServer($loop, $connector);
$socket = $proxy->listen($args['listen']);

$addr = str_replace('tcp://', 'http://', $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . PHP_EOL;

if ($args['path']) {
    echo 'Forwarding via: ' . implode(' -> ', $args['path']) . PHP_EOL;
}

$loop->run();
