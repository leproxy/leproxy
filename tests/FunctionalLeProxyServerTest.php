<?php

use LeProxy\LeProxy\LeProxyServer;
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
use LeProxy\LeProxy\ConnectorFactory;

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
        $this->socketProxy = $proxy->listen('127.0.0.1:8084', false);

        $this->proxy = $this->socketProxy->getAddress();
    }

    //private function setConnector()

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
        $this->assertNotContains("User-Agent:", $response);
    }

    public function testPlainGetWithExplicitUserAgent()
    {
        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET http://127.0.0.1:8082/ HTTP/1.1\r\nUser-Agent: custom\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
        $this->assertContains("User-Agent: custom\r\n", $response);
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

    public function testPlainGetOverUnixDomainSocketProxy()
    {
        // close TCP/IP socket and restart random Unix domain socket (UDS) path
        $this->socketProxy->close();
        $proxy = new LeProxyServer($this->loop);
        $path = tempnam(sys_get_temp_dir(), 'test');
        unlink($path);
        $this->socketProxy = $proxy->listen($path, false);
        $this->proxy = $this->socketProxy->getAddress();

        // connect to proxy and send absolute target URI
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) use ($path) {
            unlink($path);

            $conn->write("GET http://127.0.0.1:8082/ HTTP/1.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertNotContains("Server:", $response);
        $this->assertNotContains("Date:", $response);
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

    public function testConnectGetWithValidAuth()
    {
        $this->socketProxy->close();
        $proxy = new LeProxyServer($this->loop);
        $this->socketProxy = $proxy->listen('user:pass@127.0.0.1:8084', false);
        $this->proxy = $this->socketProxy->getAddress();

        // connect to proxy and send CONNECT requets and then normal request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT 127.0.0.1:8082 HTTP/1.1\r\nProxy-Authorization: Basic dXNlcjpwYXNz\r\n\r\n");

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

    public function testConnectGetWithInvalidAuthFails()
    {
        $this->socketProxy->close();
        $proxy = new LeProxyServer($this->loop);
        $this->socketProxy = $proxy->listen('user:pass@127.0.0.1:8084', false);
        $this->proxy = $this->socketProxy->getAddress();

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

        $this->assertStringStartsWith("HTTP/1.1 407 Proxy Authentication Required\r\n", $response);
        $this->assertContains("Server: LeProxy\r\n", $response);
    }

    public function testSocksGet()
    {
        // connect to proxy and send CONNECT requets and then normal request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("\x05\x01\x00" . "\x05\x01\x00\x03\x09" . "localhost" . "\x1F\x92");

            $conn->once('data', function () use ($conn) {
                $conn->once('data', function () use ($conn) {
                    $conn->write("GET / HTTP/1.1\r\n\r\n");
                });
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.2);

        $this->assertStringStartsWith("\x05\x00" . "\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00", $response);
        $this->assertContains("HTTP/1.1 200 OK\r\n", $response);
        $this->assertContains("\r\n\r\nGET / HTTP/1.1\r\n", $response);
    }

    public function testSocksBlockedWillReturnRulesetError()
    {
        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->never())->method('connect');

        $blocker = ConnectorFactory::createBlockingConnector(array('*'), $base);

        $this->socketProxy->close();
        $proxy = new LeProxyServer($this->loop, $blocker);
        $this->socketProxy = $proxy->listen('127.0.0.1:8084', false);
        $this->proxy = $this->socketProxy->getAddress();

        // connect to proxy and send CONNECT requets and then normal request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("\x05\x01\x00" . "\x05\x01\x00\x03\x09" . "localhost" . "\x1F\x92");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.2);

        $this->assertEquals("\x05\x00" . "\x05\x02\x00\x01\x00\x00\x00\x00\x00\x00", $response);
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

    public function testDirectInvalid()
    {
        // connect to proxy and send direct request
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("GET /pac HTTP\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 400 Bad Request\r\n", $response);
        //$this->assertContains("Server: LeProxy\r\n", $response);
        //$this->assertNotContains('X-Powered-By', $response);
    }

    public function testPlainPostWithChunkedTransferEncodingReturns411LengthRequired()
    {
        // connect to proxy and send request with (rare but valid) "Transfer-Encoding: chunked"
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("POST http://127.0.0.1:8082/ HTTP/1.1\r\nTransfer-Encoding: chunked\r\n\r\n0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 411 Length Required\r\n", $response);
        $this->assertContains("Server: LeProxy", $response);
        $this->assertContains("\r\n\r\nLeProxy HTTP/SOCKS proxy does not allow buffering chunked requests", $response);
    }

    public function testPlainPostWithUnknownTransferEncodingReturns501NotImplemented()
    {
        // connect to proxy and send request with unknown "Transfer-Encoding: foo"
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("POST http://127.0.0.1:8082/ HTTP/1.1\r\nTransfer-Encoding: foo\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 501 Not Implemented\r\n", $response);
        $this->assertNotContains("Server: LeProxy", $response);
    }

    public function testPlainPostWithChunkedTransferEncodingAndContentLengthReturns400BadRequest()
    {
        // connect to proxy and send invalid request with both "Transfer-Encoding: chunked" and "Content-Length"
        $connector = new Connector($this->loop);
        $promise = $connector->connect($this->proxy)->then(function (ConnectionInterface $conn) {
            $conn->write("POST http://127.0.0.1:8082/ HTTP/1.1\r\nTransfer-Encoding: chunked\r\nContent-Length: 0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($promise, $this->loop, 0.1);

        $this->assertStringStartsWith("HTTP/1.1 400 Bad Request\r\n", $response);
        $this->assertNotContains("Server: LeProxy", $response);
    }
}
