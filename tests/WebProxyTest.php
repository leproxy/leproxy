<?php

use RingCentral\Psr7\Request;
use React\Http\Response;
use React\Promise\Promise;
use React\Http\ServerRequest;
use React\Stream\ThroughStream;

class WebProxyTest extends PHPUnit_Framework_TestCase
{
    private $client;
    private $serverAddress;

    public function setUp()
    {
        $this->client = $this->getMockBuilder('\HttpClient')->disableOriginalConstructor()->getMock();
        $this->serverAddress = '127.0.0.1:1080';
    }

    public function testWebProxyCall()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web');
        $this->client->method('send')->with($request)->willReturn(
            new Promise(function ($resolve, $reject) {
                $resolve(new Response());
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);
        $result = $webProxy->handleRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
    }

    public function testReplaceRelativeLinksWithAbsoluteWebProxyLink()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withQueryParams(array('url' => urlencode('httpbin.org')));
        $stream = new ThroughStream();

        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) use ($stream) {
                $resolve(new Response(
                    200,
                    array('Content-Type' => 'text/html'),
                    $stream
                ));
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $result = '';
        $response->getBody()->on('data', function ($data) use (&$result) {
            $result .= $data;
        });

        $body = '<html>';
        $body .= '<body>';
        $body .= '<a href="/ip">';
        $body .= '<div> this is no a href="bla"</div>';
        $body .= '<a href="/link">';
        $body .= '</body>';
        $body .= '</html>';
        $stream->emit('data', array($body));
        $stream->end();

        $this->assertContains('<a href="127.0.0.1:1080/web?url=httpbin.org%2Fip">', $result);
    }

    public function testReplaceAbsoulteLinksWithWebProxyLinkgs()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withQueryParams(array('url' => urlencode('httpbin.org')));
        $stream = new ThroughStream();

        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) use ($stream) {
                $resolve(new Response(
                    200,
                    array('Content-Type' => 'text/html'),
                    $stream
                ));
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $result = '';
        $response->getBody()->on('data', function ($data) use (&$result) {
            $result .= $data;
        });

        $body = '<html>';
        $body .= '<body>';
        $body .= '<a href="http://example.com">';
        $body .= '<div> this is no a href="bla"</div>';
        $body .= '<a href="/link">';
        $body .= '</body>';
        $body .= '</html>';
        $stream->emit('data', array($body));
        $stream->end();

        $this->assertContains('<a href="127.0.0.1:1080/web?url=http%3A%2F%2Fexample.com">', $result);
    }

    public function testAlreadyEncodedLinksWontBeEncodedLinksWontBeEncoded()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=httpbin.org/redirect-to?url=http%3A%2F%2Fexample.com%2F');
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=httpbin.org/redirect-to?url=http%3A%2F%2Fexample.com%2F');
        $request = $request->withQueryParams(array('url' => 'httpbin.org/redirect-to?url=http%3A%2F%2Fexample.com%2F'));
        $stream = new ThroughStream();

        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) use ($stream) {
                $resolve(new Response(
                    200,
                    array('Content-Type' => 'text/html'),
                    $stream
                ));
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $result = '';
        $response->getBody()->on('data', function ($data) use (&$result) {
            $result .= $data;
        });

        $body = '<html>';
        $body .= '<body>';
        $body .= '<a href="http://example.com?url=http://test.com">';
        $body .= '<div> this is no a href="bla"</div>';
        $body .= '<a href="/link">';
        $body .= '</body>';
        $body .= '</html>';
        $stream->emit('data', array($body));
        $stream->end();

        $this->assertContains('<a href="127.0.0.1:1080/web?url=http%3A%2F%2Fexample.com%3Furl%3Dhttp%3A%2F%2Ftest.com">', $result);
    }

    public function testDontReplaceHtmlSpecialCharsFromResponse()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withQueryParams(array('url' => urlencode('httpbin.org')));
        $stream = new ThroughStream();

        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) use ($stream) {
                $resolve(new Response(
                    200,
                    array('Content-Type' => 'text/html'),
                    $stream
                ));
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $result = '';
        $response->getBody()->on('data', function ($data) use (&$result) {
            $result .= $data;
        });

        $body = '<html>';
        $body .= '<body>';
        $body .= '&nbsp;';
        $body .= '</body>';
        $body .= '</html>';
        $stream->emit('data', array($body));
        $stream->end();

        $this->assertContains('&nbsp;', $result);
    }

    public function testReplaceRedirectLocationWithWebProxyUrl()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withQueryParams(array('url' => urlencode('httpbin.org')));

        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) {
                $resolve(new Response(
                    302,
                    array(
                        'Content-Type' => 'text/html',
                        'Location' => 'http://example.com'
                    )
                ));
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $this->assertEquals("127.0.0.1:1080/web?url=http%3A%2F%2Fexample.com", $response->getHeaderLine('Location'));
    }

    public function testWithoutHtmlContentTypeWontBeAdapted()
    {
        $request = new ServerRequest('GET', 'http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withRequestTarget('http://127.0.0.1:1080/web?url=' . urlencode('httpbin.org'));
        $request = $request->withQueryParams(array('url' => urlencode('httpbin.org')));

        $expected = new Response();
        $this->client->method('send')->willReturn(
            new Promise(function ($resolve, $reject) use ($expected) {
                $resolve($expected);
            })
        );

        $webProxy = new WebProxy($this->client, $this->serverAddress);

        $promise = $webProxy->handleRequest($request);
        $response = null;
        $promise->then(function ($res) use (&$response) {
            $response = $res;
        });

        $this->assertSame($expected, $response);
    }
}
