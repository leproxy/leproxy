<?php

use Clue\Commander\Router;

require __DIR__ . '/vendor/autoload.php';

$listen = '127.0.0.1:1080';
$path = array();

// parse options from command line arguments (argv)
$commander = new Router();
$commander->add('[<listen> [<forward>...]]', function ($args) use (&$listen, &$path) {
    if (isset($args['listen'])) {
        $listen = $args['listen'];
    }
    if (isset($args['forward'])) {
        $path = $args['forward'];
    }
});
$commander->execArgv();

$loop = React\EventLoop\Factory::create();

// set next proxy server chain -> p1 -> p2 -> p3 -> destination
$connector = ConnectorFactory::createConnectorChain($path, $loop);

// listen on 127.0.0.1:1080 or first argument
$proxy = new LeProxyServer($loop, $connector);
$socket = $proxy->listen($listen);

$addr = str_replace('tcp://', 'http://', $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . PHP_EOL;

if ($path) {
    echo 'Forwarding via: ' . implode(' -> ', $path) . PHP_EOL;
}

$loop->run();
