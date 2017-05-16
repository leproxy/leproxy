<?php

use React\Socket\ServerInterface;
use React\Socket\ConnectorInterface;
use React\EventLoop\LoopInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Socket\ConnectionInterface;

class HttpConnectServer
{
    public function __construct(LoopInterface $loop, ServerInterface $socket, ConnectorInterface $connector)
    {
        $server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($connector) {
            if ($request->getMethod() !== 'CONNECT') {
                return new Response(
                    405,
                    array('Content-Type' => 'text/plain', 'Allow' => 'CONNECT'),
                    'This is a HTTP CONNECT (secure HTTPS) proxy'
                );
            }

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
        });

        $server->on('error', 'printf');
    }
}