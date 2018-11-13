<?php

use LeProxy\LeProxy\LoggingConnector;
use React\Promise;

class LoggingConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testConnectPendingDoesNotPrintAnything()
    {
        $promise = new Promise\Promise(function () { });

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->never())->method('logConnection');
        $logger->expects($this->never())->method('logFailConnection');
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80');
    }

    public function testConnectErrorWithoutSourcePrintsErrorWithoutSource()
    {
        $promise = Promise\reject(new RuntimeException('error'));

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logFailConnection')->with(null, 'example.com:80', 'error');
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80');
    }

    public function testConnectErrorWithSourcePrintsErrorWithSource()
    {
        $promise = Promise\reject(new RuntimeException('error'));

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with($this->stringStartsWith('example.com:80?source='))->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logFailConnection')->with('http://user:pass@host:8080', $this->stringStartsWith('example.com:80?source='), 'error');
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80?source=' . rawurlencode('http://user:pass@host:8080'));
    }

    public function testConnectErrorWithoutSourcePrintsErrorMessageWithoutConnectionTargetRepeated()
    {
        $promise = Promise\reject(new RuntimeException('Connection to X failed: reasons'));

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logFailConnection')->with(null, 'example.com:80', 'failed: reasons');
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80');
    }

    public function testConnectSuccessWithoutSourcePrintsSuccessWithoutSource()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logConnection')->with(null, 'example.com:80', null);
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80');
    }

    public function testConnectSuccessWithSourcePrintsSuccessWithSource()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with($this->stringStartsWith('example.com:80?source='))->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logConnection')->with('http://user:pass@host:8080', $this->stringStartsWith('example.com:80?source='), null);
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80?source=' . rawurlencode('http://user:pass@host:8080'));
    }

    public function testConnectSuccessWithoutRemoteIpPrintsSuccessWithDestinationAndIp()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('1.2.3.4:5060');

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $logger = $this->getMockBuilder('LeProxy\LeProxy\Logger')->getMock();
        $logger->expects($this->once())->method('logConnection')->with(null, 'example.com:80', '1.2.3.4:5060');
        $connector = new LoggingConnector($connector, $logger);

        $connector->connect('example.com:80');
    }
}
