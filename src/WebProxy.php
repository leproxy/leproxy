<?php

use React\Http\Server;
use React\Http\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7\Request;
use React\Stream\ThroughStream;

/** @internal */
class WebProxy
{
    private $server;
    private $serverAddress;

    public function __construct(HttpClient $client, $serverAddress)
    {
        $this->client = $client;
        $this->serverAddress = $serverAddress;
    }

    public function handleRequest(RequestInterface $request)
    {
        $queryParams = $request->getQueryParams();
        if ($queryParams !== null && isset($queryParams['url'])) {
            $urlArray = parse_url($queryParams['url']);

            if (isset($urlArray['path']) !== '/') {
                $url = urldecode($urlArray['path']);
            }

            if (strpos($url, 'http://') === false) {
                $url = 'http://' . $url;
            }

            if (isset($urlArray['query'])) {
                $url = $url . '?' . $urlArray['query'];
            }

            $request = new Request('GET', $url);
            $request = $request->withRequestTarget($url);

            $serverUrl = $this->serverAddress. '/web?url=';

            $that = $this;
            return $this->client->send($request)->then(function(ResponseInterface $response) use ($serverUrl, $url, $that) {

                $contentType = '';
                if ($response->hasHeader('Content-Type')) {
                    $contentType = $response->getHeaderLine('Content-Type');
                }

                if (strpos($contentType, 'html') === false) {
                    return $response;
                }

                $host = parse_url($url)['host'];
                if ($response->getStatusCode() === 302) {
                    $location = $that->replacePathForProxy($serverUrl, $host, $response->getHeaderLine('Location'));
                    $response = $response->withHeader('Location', $location);
                    return $response;
                }

                $stream = new ThroughStream();

                $result = new Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $stream,
                    $response->getProtocolVersion(),
                    $response->getReasonPhrase()
                );

                $buffer = '';
                $response->getBody()->on('data', function ($data) use (&$buffer) {
                    $buffer .= $data;
                });

                $response->getBody()->on('end', function () use ($serverUrl, $host, $stream, &$buffer, $that) {
                    $doc = new DOMDocument();
                    $encoding = mb_detect_encoding($buffer, array('UTF-8', 'ISO-8859-1'));
                    $buffer = mb_convert_encoding($buffer, 'HTML-ENTITIES', $encoding);
                    @$doc->loadHTML($buffer, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $xpath = new DOMXPath($doc);

                    foreach($xpath->query("//*[@href]") as $element) {
                        $path = $element->getAttribute('href');
                        $element->setAttribute('href', $that->replacePathForProxy($serverUrl, $host, $path));
                    }

                    $body = $doc->saveHTML();
                    $stream->emit('data', array($body));
                    $stream->end();
                });

                return $result;
            });
        }

        $body = '<form>';
        $body .= '<div align="center" style="margin-top: 15%">';
        $body .= '<input type="text" name="url" label="Enter a URL e.g. http://youtube.com" style="width: 25%">';
        $body .= '<input type="submit" value="Execute via LeProxy">';
        $body .= '</div>';
        $body .= '</form>';

        return new Response(
            200,
            array(
                'Content-Type' => 'text/html'
            ),
            $body
        );
    }

    /** @internal */
    public function replacePathForProxy($baseUrl, $host, $path)
    {
        if (parse_url($path, PHP_URL_SCHEME) == '') {
            $path = $host . $path;
        }

        return $baseUrl. urlencode($path);
    }
}
