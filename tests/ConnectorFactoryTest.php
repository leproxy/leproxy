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

    public function testCoerceListenUri()
    {
        $uris = array(
            '' => '0.0.0.0:8080',
            '127.0.0.1:1234' => '127.0.0.1:1234',
            '127.0.0.1' => '127.0.0.1:8080',
            '127.0.0.1:0' => '127.0.0.1:0',
            ':1234' => '0.0.0.0:1234',
            ':0' => '0.0.0.0:0',
            'user:pass@0.0.0.0:8080' => 'user:pass@0.0.0.0:8080',
            'user:pass@127.0.0.1' => 'user:pass@127.0.0.1:8080',
            'user:pass@:1234' => 'user:pass@0.0.0.0:1234',
            '12:34@:45' => '12:34@0.0.0.0:45',
            'user:pass@' => 'user:pass@0.0.0.0:8080',
            '[::1]' => '[::1]:8080',
            'user:pass@[::1]' => 'user:pass@[::1]:8080'
        );

        foreach ($uris as $in => $out) {
            $this->assertEquals($out, ConnectorFactory::coerceListenUri($in));
        }
    }

    public function testCoerceListenUriInvalidThrows()
    {
        $uris = array(
            'invalid port' => '127.0.0.1:port',
            'hostname' => 'localhost:8080',
            'wildcard hostname' => '*:8080',
            'excessive scheme' => 'http://127.0.0.1:8080',
            'excessive path' => '127.0.0.1:8080/root',
            'excessive query' => '127.0.0.1:8080?query',
            'excessive fragment' => '127.0.0.1:8080#fragment',
        );

        foreach ($uris as $uri) {
            try {
                ConnectorFactory::coerceListenUri($uri);
                $this->fail();
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testIsIpLocal()
    {
        $ips = array(
            '127.0.0.1' => true,
            '127.1.2.3' => true,
            '192.168.1.1' => false,
            '8.8.8.8' => false,

            '::ffff:127.0.0.1' => true,
            '::1' => true,
            '::2' => false
        );

        foreach ($ips as $ip => $bool) {
            $this->assertEquals($bool, ConnectorFactory::isIpLocal($ip));
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

    public function testEmptyBlockPassesThrough()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('google.com:80');

        $connector = ConnectorFactory::createBlockingConnector(array(), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $connector->connect('google.com:80');
    }

    public function testBlockDomainsBlocksOnlyDomainsAndSubDomains()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('tls://github.com:443');

        $connector = ConnectorFactory::createBlockingConnector(array('google.com', 'google.de'), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);

        $this->assertPromiseRejected($connector->connect('google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://google.com:80'));
        $this->assertPromiseRejected($connector->connect('tls://google.com:443'));

        $this->assertPromiseRejected($connector->connect('tcp://www.google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://some.sub.domain.google.de:80'));

        $connector->connect('tls://github.com:443');
    }

    public function testBlockWildcardBlocksOnlyMatchingDomains()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('tcp://google.com:80');

        $connector = ConnectorFactory::createBlockingConnector(array('*.google.com'), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);

        $this->assertPromiseRejected($connector->connect('test.google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://test.google.com:80'));
        $this->assertPromiseRejected($connector->connect('tls://test.google.com:443'));
        $this->assertPromiseRejected($connector->connect('tcp://some.sub.domain.google.com:80'));

        $connector->connect('tcp://google.com:80');
    }

    public function testBlockHttpPort()
    {
        $allow = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $allow->expects($this->once())->method('connect')->with('tls://google.com:443');

        $connector = ConnectorFactory::createBlockingConnector(array(':80'), $allow);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
        $this->assertPromiseRejected($connector->connect('google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://google.com:80'));
        $this->assertPromiseRejected($connector->connect('tcp://github.com:80'));

        $connector->connect('tls://google.com:443');
    }

    public function testBlockHttpPortWildcardDomain()
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
