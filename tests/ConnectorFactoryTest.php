<?php

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
}
