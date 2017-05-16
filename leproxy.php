<?php

use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use Clue\React\Socks\Server as SocksServer;
use React\Socket\Server as Socket;
use React\Socket\Connector;

require __DIR__ . '/vendor/autoload.php';

$listen = isset($argv[1]) ? $argv[1] : 1080;
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
$address = $listen;
$pos = strpos($address, '://');
$schema = 'socks';
if ($pos !== false) {
    $schema = substr($address, 0, $pos);
    if (!in_array($schema, array('socks', 'http'))) {
        throw new \InvalidArgumentException('Invalid URI schema "' . $schema . '" in listening address');
    }
    $address = substr($address, $pos + 3);
}
$socket = new Socket($address, $loop);

// start new proxy server which uses the above connector for forwarding
if ($schema === 'http') {
    $server = new HttpConnectServer($loop, $socket, $connector);
} else {
    $server = new SocksServer($loop, $socket, $connector);
}

$addr = str_replace('tcp://', $schema . '://', $socket->getAddress());
echo 'LeProxy is now listening on ' . $addr . PHP_EOL;
if ($path) {
    echo 'Forwarding via: ' . implode(' -> ', $path) . PHP_EOL;
}

$loop->run();
