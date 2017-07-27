<?php

use LeProxy\LeProxy\LoggingConnector;
use React\Promise;

class LoggingConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testConnectPendingDoesNotPrintAnything()
    {
        $this->expectOutputString('');

        $promise = new Promise\Promise(function () { });

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80');
    }

    public function testConnectErrorWithoutSourcePrintsErrorWithoutSource()
    {
        $this->expectOutputRegex('/\?\?\? failed to connect to example\.com:80 \(error\)\s+$/');

        $promise = Promise\reject(new RuntimeException('error'));

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80');
    }

    public function testConnectErrorWithSourcePrintsErrorWithSource()
    {
        $this->expectOutputRegex('/http:\/\/user@host failed to connect to example\.com:80 \(error\)\s+$/');

        $promise = Promise\reject(new RuntimeException('error'));

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80?source=http%3A//user:pass@host:8080')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80?source=http%3A//user:pass@host:8080');
    }

    public function testConnectSuccessWithoutSourcePrintsSuccessWithoutSource()
    {
        $this->expectOutputRegex('/\?\?\? connected to example\.com:80\s+$/');

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80');
    }

    public function testConnectSuccessWithSourcePrintsSuccessWithSource()
    {
        $this->expectOutputRegex('/http:\/\/user@host connected to example\.com:80\s+$/');

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80?source=http%3A//user:pass@host:8080')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80?source=http%3A//user:pass@host:8080');
    }

    public function testConnectSuccessWithoutRemoteIpPrintsSuccessWithDestinationAndIp()
    {
        $this->expectOutputRegex('/\?\?\? connected to example\.com:80 \(1\.2\.3\.4:5060\)\s+$/');

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('1.2.3.4:5060');

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('example.com:80');
    }

    public function testConnectSuccessWithoutSameRemoteIpPrintsSuccessWithDestinationWithoutIp()
    {
        $this->expectOutputRegex('/\?\?\? connected to 1\.2\.3\.4:5060\s+$/');

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('1.2.3.4:5060');

        $promise = Promise\resolve($connection);

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('1.2.3.4:5060')->willReturn($promise);

        $connector = new LoggingConnector($connector);

        $connector->connect('1.2.3.4:5060');
    }
}
