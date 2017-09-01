<?php

use LeProxy\LeProxy\SocksErrorConnector;
use React\Promise\Promise;
use LeProxy\LeProxy\ConnectorFactory;

class SocksErrorConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testConnectWillBePassedThrough()
    {
        $promise = new Promise(function () { });

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base);

        $ret = $connector->connect('example.com:1234');

        $this->assertInstanceOf('React\Promise\PromiseInterface', $ret);
    }

    public function testConnectWillThrowErrorForBlockedConnection()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', ConnectorFactory::CODE_BLOCKED));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(SOCKET_EACCES, $code);
    }

    public function testConnectWillThrowErrorForDirectError()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', SOCKET_ECONNREFUSED));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(SOCKET_ECONNREFUSED, $code);
    }

    public function testConnectWillThrowGenericErrorForNestedError()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', SOCKET_ECONNREFUSED, new RuntimeException()));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(0, $code);
    }
}
