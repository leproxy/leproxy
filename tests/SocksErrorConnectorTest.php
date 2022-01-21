<?php

use LeProxy\LeProxy\ConnectorFactory;
use LeProxy\LeProxy\SocksErrorConnector;
use React\Promise\Promise;

class SocksErrorConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testConnectWillBePassedThrough()
    {
        $promise = new Promise(function () { });

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base, false);

        $ret = $connector->connect('example.com:1234');

        $this->assertInstanceOf('React\Promise\PromiseInterface', $ret);
    }

    public function testConnectWillThrowErrorForBlockedConnection()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', ConnectorFactory::CODE_BLOCKED));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base, false);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(defined('SOCKET_EACCES') ? SOCKET_EACCES : 13, $code);
    }

    public function testConnectWillThrowErrorForDirectError()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base, false);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111, $code);
    }

    public function testConnectWillThrowGenericErrorForNestedError()
    {
        $promise = \React\Promise\reject(new RuntimeException('test', defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111, new RuntimeException()));

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('example.com:1234')->willReturn($promise);

        $connector = new SocksErrorConnector($base, false);

        $ret = $connector->connect('example.com:1234');

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(0, $code);
    }

    public function testConnectWillBePassedThroughWhenLocalModeIsEnabledAndSourceIsLocal()
    {
        $promise = new Promise(function () { });

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->willReturn($promise);

        $connector = new SocksErrorConnector($base, true);

        $ret = $connector->connect('example.com:1234?source=' . rawurlencode('socks://127.0.0.1'));

        $this->assertInstanceOf('React\Promise\PromiseInterface', $ret);
    }

    public function testConnectWillBePassedThroughWhenLocalModeIsDisabledAndSourceIsRemote()
    {
        $promise = new Promise(function () { });

        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->willReturn($promise);

        $connector = new SocksErrorConnector($base, false);

        $ret = $connector->connect('example.com:1234?source=' . rawurlencode('socks://1.2.3.4'));

        $this->assertInstanceOf('React\Promise\PromiseInterface', $ret);
    }

    public function testConnectWillNotBePassedThroughWhenLocalModeIsEnabledAndSourceIsRemote()
    {
        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->never())->method('connect');

        $connector = new SocksErrorConnector($base, true);

        $ret = $connector->connect('example.com:1234?source=' . rawurlencode('socks://1.2.3.4'));

        $this->assertInstanceOf('React\Promise\PromiseInterface', $ret);

        $code = null;
        $ret->then(null, function ($e) use (&$code) {
            $code = $e->getCode();
        });

        $this->assertEquals(defined('SOCKET_EACCES') ? SOCKET_EACCES : 13, $code);
    }
}
