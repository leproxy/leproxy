<?php

use LeProxy\LeProxy\ConnectorFactory;

class ConnectorFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testEmptyChainReturnsConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = ConnectorFactory::createConnectorChain(array(), $loop);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertInstanceOf('React\Socket\Connector', $connector);
    }

    public function testChainWithUnspecifiedProxyReturnsHttpConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = ConnectorFactory::createConnectorChain(array('127.0.0.1:1080'), $loop);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertInstanceOf('Clue\React\HttpProxy\ProxyConnector', $connector);
    }

    public function testChainWithHttpProxyReturnsHttpConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = ConnectorFactory::createConnectorChain(array('http://127.0.0.1:1080'), $loop);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertInstanceOf('Clue\React\HttpProxy\ProxyConnector', $connector);
    }

    public function testChainWithSocksProxyReturnsSocksConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = ConnectorFactory::createConnectorChain(array('socks://127.0.0.1:1080'), $loop);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertInstanceOf('Clue\React\Socks\Client', $connector);
    }

    public function testChainWithMixedProxiesReturnsAnyConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = ConnectorFactory::createConnectorChain(array('socks://127.0.0.1:1080', 'http://127.0.0.1:1080'), $loop);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
    }

    /** @expectedException InvalidArgumentException */
    public function testThrowsIfChainContainsInvalidUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        ConnectorFactory::createConnectorChain(array('///'), $loop);
    }

    public function testEmptyBlockPassesThrough()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('google.com:80');

        $connector = ConnectorFactory::createBlockingConnector(array(), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $connector->connect('google.com:80');
    }

    public function testBlockDomains()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('tls://github.com:443');

        $connector = ConnectorFactory::createBlockingConnector(array('google.com', 'google.de'), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);

        $this->assertPromiseRejected($connector->connect('google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://google.com:80'));
        $this->assertPromiseRejected($connector->connect('tls://google.com:443'));

        $connector->connect('tls://github.com:443');
    }

    public function testBlockHttpPort()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('tls://google.com:443');

        $connector = ConnectorFactory::createBlockingConnector(array('*:80'), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertPromiseRejected($connector->connect('google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://github.com:80'));

        $connector->connect('tls://google.com:443');
    }

    private function assertPromiseRejected($input)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $input);

        $rejected = false;
        $input->then(null, function () use (&$rejected) {
            $rejected= true;
        });

        $this->assertTrue($rejected);
    }
}
