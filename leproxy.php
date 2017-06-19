<?php

require __DIR__ . '/vendor/autoload.php';

$listen = isset($argv[1]) ? $argv[1] : '127.0.0.1:1080';
$path = isset($argv[2]) ? array_slice($argv, 2) : array();

// Alternatively, you can also hard-code these values like this:
//$listen = '127.0.0.1:9050';
//$path = array('127.0.0.1:9051', '127.0.0.1:9052', '127.0.0.1:9053');

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
