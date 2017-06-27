<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Server;
use React\Socket\Server as Socket;
use React\EventLoop\Factory;
use React\Socket\Connector;
use RingCentral\Psr7\Response;
use React\Socket\ConnectionInterface;
use Clue\React\Block;
use React\Promise\Stream;
use RingCentral\Psr7;

class FunctionalLeProxyServerTest extends PHPUnit_Framework_TestCase
{
    private $loop;
    private $socketProxy;
    private $socketOrigin;
    private $proxy;

    private $headers = array();

    public function setUp()
    {
        $this->loop = Factory::create();

        $this->socketOrigin = new Socket(8082, $this->loop);

        $origin = new Server(function (ServerRequestInterface $request) {
            return new Response(
                200,
                $this->headers + array(
                    'X-Powered-By' => '',
                    'Date' => '',
                ),
                Psr7\str($request)
            );
        });
        $origin->listen($this->socketOrigin);

        $proxy = new LeProxyServer($this->loop);
        $this->socketProxy = $proxy->listen('127.0.0.1:8084');

        $this->proxy = $this->socketProxy->getAddress();
    }

    public function tearDown()
    {
        $this->socketOrigin->close();
        $this->socketProxy->close();
    }

    public function testPlainGetReturnsUpstreamResponseHeaders()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:8082/ HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertNotContains("Server:", $response);
        $this->assertNotContains("Date:", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
    }

    public function testPlainGetReturnsUpstreamResponseHeadersCustom()
    {
        $this->headers = array(
            'Server' => 'React',
            'Date' => 'Tue, 27 Jun 2017 12:52:16 GMT',
            'X-Powered-By' => 'React'
        );
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:8082/ HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("Server: React\r\n", $response);
        $this->assertContains("Date: Tue, 27 Jun 2017 12:52:16 GMT", $response);
        $this->assertContains("X-Powered-By: React\r\n", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
    }

    public function testPlainGetInvalidUriReturns502WithProxyResponseHeaders()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:2/ HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 502 Bad Gateway\r\n", $response);
        $this->assertContains("Server: LeProxy\r\n", $response);
        $this->assertContains("\r\n\r\nUnable to request:", $response);
    }

    public function testPlainGetWithoutPathUsesRootPath()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:8082 HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertNotContains("Server:", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
    }

    public function testPlainOptions()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("OPTIONS http://127.0.0.1:8082/ HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertNotContains("Server:", $response);
        $this->assertContains("\r\n\r\nOPTIONS / HTTP/1.1\r\n", $response);
    }

    public function testPlainOptionsWithoutPathUsesAsteriskForm()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("OPTIONS http://127.0.0.1:8082 HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertNotContains("Server:", $response);
        $this->assertContains("\r\n\r\nOPTIONS * HTTP/1.1\r\n", $response);
    }

    public function testConnectGet()
    {
        // connect to proxy and send CONNECT requets and then normal request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT 127.0.0.1:8082 HTTP/1.1\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write("GET / HTTP/1.1\r\n\r\n");
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.2);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("Server: LeProxy\r\n", $response);
        $this->assertContains("\r\n\r\nHTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
    }

    public function testConnectInvalidUriReturns502()
    {
        // connect to proxy and send CONNECT request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT 127.0.0.1:2 HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 502 Bad Gateway\r\n", $response);
        $this->assertContains("Server: LeProxy\r\n", $response);
        $this->assertContains("\r\n\r\nUnable to connect:", $response);
    }

    public function testPacDirect()
    {
        // connect to proxy and send direct request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET /pac HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("Server: LeProxy\r\n", $response);
        $this->assertContains("PROXY", $response);
    }

    public function testPacInvalidMethod()
    {
        // connect to proxy and send direct request with non-GET method
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("POST /pac HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 405 Method Not Allowed\r\n", $response);
    }

    public function testPacPlain()
    {
        // connect to proxy and send plain request
        $connector = new Connector($this->loop);

        $uri = str_replace('tcp:', 'http:', $this->proxy);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) use ($uri) {
            $conn->write("GET $uri/pac HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("PROXY", $response);
    }

    public function testPacPlainToInvalidHostWillReturnError()
    {
        // connect to proxy and send plain request to other host
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:2/pac HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 502 Bad Gateway\r\n", $response);
        $this->assertContains("\r\n\r\nUnable to request:", $response);
    }

    public function testPacConnect()
    {
        // connect to proxy and send CONNECT request
        $connector = new Connector($this->loop);

        $uri = str_replace('tcp://', '', $this->proxy);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) use ($uri) {
            $conn->write("CONNECT $uri HTTP/1.1\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write("GET /pac HTTP/1.1\r\n\r\n");
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.2);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("\r\n\r\nHTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("PROXY", $response);
    }
}
