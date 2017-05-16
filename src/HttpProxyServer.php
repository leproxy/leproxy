<?php

use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ServerInterface;
use React\Promise\Promise;
use React\Stream\ThroughStream;
use React\Http\HttpBodyStream;

class HttpProxyServer
{
    public function __construct(LoopInterface $loop, ServerInterface $socket, ConnectorInterface $connector)
    {
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
        if ($request->getBody()->getSize() === null) {
            return new Response(
                411,
                array('Content-Type' => 'text/plain'),
                'The server refuses to accept the request without a defined Content- Length header'
            );
        }

        // prepare outgoing client request by updating request-target and Host header
        $host = (string)$request->getUri()->withScheme('')->withPath('')->withQuery('');
        $target = (string)$request->getUri()->withScheme('')->withHost('')->withPort(null);
        if ($target === '') {
            $target = $request->getMethod() === 'OPTIONS' ? '*' : '/';
        }
        $outgoing = $request->withRequestTarget($target)->withHeader('Host', $host);

        $connect = $request->getUri()->withScheme('tcp')->withUserInfo('')->withPath('')->withQuery('');
        if ($connect->getPort() === null) {
            $connect = $connect->withPort(80);
        }

        // pause consuming request body
        $body = $request->getBody();
        $body->pause();

        $buffer = '';
        $body->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        // try to connect to given target host
        return $connector->connect($connect)->then(
            function (ConnectionInterface $remote) use ($body, &$buffer, $outgoing) {
                // write outgoing request headers
                $remote->write(Psr7\str($outgoing));

                // connection established => forward data
                $body->pipe($remote);
                $body->resume();

                if ($buffer !== '') {
                    $remote->write($buffer);
                    $buffer = '';
                }

                return new Promise(function ($resolve, $reject) use ($remote) {
                    $remote->on('data', function ($chunk) use (&$buffer, $resolve, $remote) {
                        $buffer .= $chunk;
                        $pos = strpos($buffer, "\r\n\r\n");

                        if ($pos !== false) {
                            try {
                                $response = Psr7\parse_response(substr($buffer, 0, $pos));
                            } catch (\Exception $e) {
                                $resolve(new Response(
                                    502,
                                    array('Content-Type' => 'text/plain'),
                                    'Invalid response: ' . $e->getMessage()
                                ));
                                $remote->close();
                                return;
                            }

                            $stream = new ThroughStream();
                            $response = $response->withBody(new HttpBodyStream($stream, null));

                            $resolve($response);

                            $buffer = (string)substr($buffer, $pos + 4);
                            if ($buffer !== null) {
                                $stream->emit('data', array($buffer));
                                $buffer = '';
                            }

                            $remote->pipe($stream);
                        }
                    });
                });

                return new Response(
                    200,
                    array(),
                    $remote
                );
            },
            function ($e) use ($connect) {
                return new Response(
                    502,
                    array('Content-Type' => 'text/plain'),
                    'Unable to connect to ' . $connect . ': ' . $e->getMessage()
                );
            }
        );
    }
}