<?php

use React\Http\ServerRequest;

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
}
