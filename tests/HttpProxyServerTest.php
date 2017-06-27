<?php

use LeProxy\LeProxy\HttpProxyServer;
use React\Http\ServerRequest;
use React\Http\HttpBodyStream;
use React\Stream\ThroughStream;

class HttpProxyServerTest extends PHPUnit_Framework_TestCase
{
    public function testCtor()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $server = new HttpProxyServer($loop, $socket, $connector);
    }

    public function testRequestWithoutAuthenticationReturnsAuthenticationRequired()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $server = new HttpProxyServer($loop, $socket, $connector);
        $server->setAuthArray(array('user' => 'pass'));

        $request = new ServerRequest('GET', '/');

        $response = $server->handleRequest($request);

        $this->assertEquals(407, $response->getStatusCode());
    }

    public function testRequestWithInvalidAuthenticationReturnsAuthenticationRequired()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $server = new HttpProxyServer($loop, $socket, $connector);
        $server->setAuthArray(array('user' => 'pass'));

        $request = new ServerRequest('GET', '/', array('Proxy-Authorization' => 'Basic dXNlcg=='));

        $response = $server->handleRequest($request);

        $this->assertEquals(407, $response->getStatusCode());
    }

    public function testRequestWithValidAuthenticationReturnsSuccess()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $server = new HttpProxyServer($loop, $socket, $connector);
        $server->setAuthArray(array('user' => 'pass'));

        $request = new ServerRequest('GET', '/', array('Proxy-Authorization' => 'Basic dXNlcjpwYXNz'));

        $response = $server->handleRequest($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testPlainRequestWithValidAuthenticationForwardsViaHttpClientWithoutAuthorizationHeader()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $socket = $this->getMockBuilder('React\Socket\ServerInterface')->getMock();
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $outgoing = $this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock();

        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())
               ->method('request')
               ->with('GET', 'http://example.com/', array('Cookie' => 'name=value'))
               ->willReturn($outgoing);

        $server = new HttpProxyServer($loop, $socket, $connector, $client);
        $server->setAuthArray(array('user' => 'pass'));

        $request = new ServerRequest('GET', 'http://example.com/', array('Proxy-Authorization' => 'Basic dXNlcjpwYXNz', 'Cookie' => 'name=value'));
        $request = $request->withRequestTarget((string)$request->getUri());
        $request = $request->withBody(new HttpBodyStream(new ThroughStream(), null));

        $promise = $server->handleRequest($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }
}
