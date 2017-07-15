<?php

use LeProxy\LeProxy\ConnectorFactory;

class ConnectorFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCoerceProxyUri()
    {
        $uris = array(
            'host' => 'http://host:8080',
            'host:1234' => 'http://host:1234',
            'socks://host' => 'socks://host:8080',
            'socks://user:pass@host' => 'socks5://user:pass@host:8080',
            'user@host' => 'http://user:@host:8080',
            'socks4a://10.20.30.40:5060' => 'socks4a://10.20.30.40:5060',
        );

        foreach ($uris as $in => $out) {
            $this->assertEquals($out, ConnectorFactory::coerceProxyUri($in));
        }
    }

    public function testCoerceProxyUriInvalidThrows()
    {
        $uris = array(
            'empty' => '',
            'invalid scheme' => 'tcp://test',
            'invalid port' => 'host:port',
            'auth for invalid scheme' => 'socks4://user@host',
            'excessive path' => 'host/root',
            'excessive query' => 'host?query',
            'excessive fragment' => 'host#fragment',
        );

        foreach ($uris as $uri) {
            try {
                ConnectorFactory::coerceProxyUri($uri);
                $this->fail();
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

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
