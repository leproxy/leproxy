<?php

use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use Clue\React\Socks\Server as SocksServer;
use React\Socket\Server as Socket;
use React\Socket\Connector;

require __DIR__ . '/vendor/autoload.php';

$listen = isset($argv[1]) ? $argv[1] : '127.0.0.1:1080';
$path = isset($argv[2]) ? array_slice($argv, 2) : array();

// Alternatively, you can also hard-code these values like this:
//$listen = '127.0.0.1:9050';
//$path = array('127.0.0.1:9051', '127.0.0.1:9052', '127.0.0.1:9053');

$loop = React\EventLoop\Factory::create();

// set next SOCKS server chain -> p1 -> p2 -> p3 -> destination
$connector = new Connector($loop);
foreach ($path as $proxy) {
    if (strpos($proxy, 'http://') === 0) {
        $connector = new HttpClient($proxy, $connector);
    } else {
        $connector = new SocksClient($proxy, $connector);
    }
}

// listen on 127.0.0.1:1080 or first argument
$pos = strpos($listen, '://');
if ($pos === false) {
    $listen = 'http://' . $listen;
}

$parts = parse_url($listen);
if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['port'])) {
    throw new \InvalidArgumentException('Invalid URI for listening address');
}

$socket = new Socket($parts['host'] . ':' . $parts['port'], $loop);

// start new proxy server which uses the above connector for forwarding
$unification = new ProtocolDetector($socket);
$http = new HttpProxyServer($loop, $unification->http, $connector);
$socks = new SocksServer($loop, $unification->socks, $connector);

$addr = str_replace('tcp://', 'http://', $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . PHP_EOL;
if ($path) {
    echo 'Forwarding via: ' . implode(' -> ', $path) . PHP_EOL;
}

$loop->run();
