<?php

use React\Promise\Deferred;
use React\HttpClient\Response as ClientResponse;
use React\Http\Response;
use React\HttpClient\Client as ReactHttpClient;
use React\Stream\ReadableStreamInterface;
use Psr\Http\Message\RequestInterface;

/** @internal */
class HttpClient
{
    private $client;

    public function __construct(ReactHttpClient $client)
    {
        $this->client = $client;
    }

    public function send(RequestInterface $request)
    {
        $headers = array();
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $outgoing = $this->client->request(
            $request->getMethod(),
            (string)$request->getUri(),
            $headers,
            $request->getProtocolVersion()
        );

        $deferred = new Deferred(function () use ($outgoing) {
            $outgoing->close();
            throw new \RuntimeException('Request cancelled');
        });

        $outgoing->on('response', function (ClientResponse $response) use ($deferred) {
            $deferred->resolve(new Response(
                $response->getCode(),
                $response->getHeaders(),
                $response,
                $response->getVersion(),
                $response->getReasonPhrase()
            ));
        });

        $outgoing->on('error', function (Exception $e) use ($deferred) {
            $deferred->reject($e);
        });

        if ($request->getBody() instanceof ReadableStreamInterface) {
            $request->getBody()->pipe($outgoing);
        }
        else {
            $outgoing->end();
        }

        return $deferred->promise();
    }
}
