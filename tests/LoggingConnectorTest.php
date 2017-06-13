<?php

use React\Stream\ThroughStream;
use React\Promise\Promise;
use LeProxy\LeProxy\LoggingConnector;

class LoggingConnectorTest extends PHPUnit_Framework_TestCase
{
    private $httpConnector;
    private $socksConnector;
    private $stream;

    public function setUp()
    {
        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->getMock();
        $connection->method('getLocalAddress')->willReturn('tcp://192.168.1.2');

        $this->httpConnector = $this->getMockBuilder('Clue\React\HttpProxy\ProxyConnector')->disableOriginalConstructor()->getMock();
        $this->httpConnector
            ->method('connect')
            ->willReturn(new Promise(function ($resolve, $reject) use ($connection) {
                $resolve($connection);
            }));

        $this->socksConnector = $this->getMockBuilder('Clue\React\Socks\Client')->disableOriginalConstructor()->getMock();
        $this->socksConnector
            ->method('connect')
            ->willReturn(new Promise(function ($resolve, $reject) use ($connection) {
                $resolve($connection);
            }));

        $this->stream = new ThroughStream();
    }

    public function testWithoutAuthWillBeLogged()
    {
        $result= '';
        $this->stream->on('data', function($data) use (&$result) {
            $result .= $data;
        });

        $logging = new LoggingConnector($this->httpConnector, $this->stream);
        $logging->connect('google.com:443');

        $this->assertContains('connected http://192.168.1.2 to tcp://google.com:443', $result);
    }

    public function testWithAuthWillBeLogged()
    {

        $result= '';
        $this->stream->on('data', function($data) use (&$result) {
            $result .= $data;
        });

        $logging = new LoggingConnector($this->httpConnector, $this->stream);
        $logging->setAuth(array('legionth' => 'test'));

        $logging->connect('google.com:443');

        $this->assertContains('connected legionth@http://192.168.1.2 to tcp://google.com:443', $result);
    }

    public function testSocksProtocolWillBeLogged()
    {
        $result= '';
        $this->stream->on('data', function($data) use (&$result) {
            $result .= $data;
        });

        $logging = new LoggingConnector($this->socksConnector, $this->stream);
        $logging->connect('google.com:443');

        $this->assertContains('connected socks5://192.168.1.2 to tcp://google.com:443', $result);
    }
}
