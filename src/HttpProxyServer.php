<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Response as ClientResponse;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ServerInterface;

class HttpProxyServer
{
    private $client;

    public function __construct(LoopInterface $loop, ServerInterface $socket, ConnectorInterface $connector, HttpClient $client = null)
    {
        if ($client === null) {
            $client = new HttpClient($loop, $connector);
        }

        $this->client = $client;

        $that = $this;
        $server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($connector, $that) {
            if (strpos($request->getRequestTarget(), '://') !== false) {
                return $that->handlePlainRequest($request, $connector);
            }

            if ($request->getMethod() === 'CONNECT') {
                return $that->handleConnectRequest($request, $connector);
            }

            return new Response(
                405,
                array('Content-Type' => 'text/plain', 'Allow' => 'CONNECT'),
                'This is a HTTP CONNECT (secure HTTPS) proxy'
            );
        });

        $server->on('error', 'printf');
    }

    /** @internal */
    public function handleConnectRequest(ServerRequestInterface $request, ConnectorInterface $connector)
    {
        // pause consuming request body
        $body = $request->getBody();
        $body->pause();

        $buffer = '';
        $body->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        // try to connect to given target host
        $promise = $connector->connect($request->getRequestTarget())->then(
            function (ConnectionInterface $remote) use ($body, &$buffer) {
                // connection established => forward data
                $body->pipe($remote);
                $body->resume();

                if ($buffer !== '') {
                    $remote->write($buffer);
                    $buffer = '';
                }

                return new Response(
                    200,
                    array(),
                    $remote
                );
            },
            function ($e) {
                return new Response(
                    502,
                    array('Content-Type' => 'text/plain'),
                    'Unable to connect: ' . $e->getMessage()
                );
            }
        );

        // cancel pending connection if request closes prematurely
        $body->on('close', function () use ($promise) {
            $promise->cancel();
        });

        return $promise;
    }

    /** @internal */
    public function handlePlainRequest(ServerRequestInterface $request, ConnectorInterface $connector)
    {
        $incoming = $request->withoutHeader('Host')->withoutHeader('Connection');

        $headers = array();
        foreach ($incoming->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $outgoing = $this->client->request(
            $incoming->getMethod(),
            (string)$incoming->getUri(),
            $headers,
            $incoming->getProtocolVersion()
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
            $message = '';
            while ($e !== null) {
                $message .= $e->getMessage() . "\n";
                $e = $e->getPrevious();
            }

            $deferred->resolve(new Response(
                502,
                array('Content-Type' => 'text/plain'),
                'Unable to request: ' . $message
            ));
        });

        $incoming->getBody()->pipe($outgoing);

        return $deferred->promise();
    }
}