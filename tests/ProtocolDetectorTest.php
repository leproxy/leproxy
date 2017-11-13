<?php

use LeProxy\LeProxy\ProtocolDetector;
use React\Socket\Server;
use React\Socket\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class ProtocolDetectorTest extends TestCase
{
    public function testCtor()
    {
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();

        $detector = new ProtocolDetector($socket);
    }

    public function testForwardsSocketErrorToHttp()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $socket = new Server(0, $loop);
        $socket->close();

        $detector = new ProtocolDetector($socket);

        $n = 0;
        $detector->http->on('error', function (Exception $e) use (&$n) {
            ++$n;
        });

        $socket->emit('error', array(new \RuntimeException()));

        $this->assertEquals(1, $n);
    }

    public function testForwardsHttpRequestToHttp()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $socket = new Server(0, $loop);
        $socket->close();

        $detector = new ProtocolDetector($socket);

        $received = null;
        $detector->http->on('connection', function (ConnectionInterface $conn) use (&$received) {
            $received = true;
            $conn->on('data', function ($chunk) use (&$received) {
                $received = $chunk;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close'))->getMock();
        $socket->emit('connection', array($connection));

        $connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertEquals("GET / HTTP/1.0\r\n\r\n", $received);
    }

    public function testForwardsSocksRequestToSocks()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $socket = new Server(0, $loop);
        $socket->close();

        $detector = new ProtocolDetector($socket);

        $received = null;
        $detector->socks->on('connection', function (ConnectionInterface $conn) use (&$received) {
            $received = true;
            $conn->on('data', function ($chunk) use (&$received) {
                $received = $chunk;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close'))->getMock();
        $socket->emit('connection', array($connection));

        $connection->emit('data', array("\x05test"));

        $this->assertEquals("\x05test", $received);
    }
}
