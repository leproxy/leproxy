<?php

namespace LeProxy\LeProxy;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Response as ClientResponse;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ServerInterface;
use Exception;

class HttpProxyServer
{
    private $connector;
    private $client;
    private $auth = null;

    public function __construct(LoopInterface $loop, ServerInterface $socket, ConnectorInterface $connector, HttpClient $client = null)
    {
        if ($client === null) {
            $client = new HttpClient($loop, $connector);
        }

        $this->connector = $connector;
        $this->client = $client;

        $that = $this;
        $server = new \React\Http\Server(array($this, 'handleRequest'));
        $server->listen($socket);
    }

    public function setAuthArray(array $auth)
    {
        $this->auth = $auth;
    }

    /** @internal */
    public function handleRequest(ServerRequestInterface $request)
    {
        // direct (origin / non-proxy) requests start with a slash
        $direct = substr($request->getRequestTarget(), 0, 1) === '/';

        if ($direct && $request->getUri()->getPath() === '/pac') {
            return $this->handlePac($request);
        }

        if ($this->auth !== null) {
            $auth = null;
            $value = $request->getHeaderLine('Proxy-Authorization');
            if (strpos($value, 'Basic ') === 0) {
                $value = base64_decode(substr($value, 6), true);
                if ($value !== false) {
                    $auth = explode(':', $value, 2) + array(1 => '');
                }
            }

            if (!$auth || !isset($this->auth[$auth[0]]) || $this->auth[$auth[0]] !== $auth[1]) {
                return new Response(
                    407,
                    array('Proxy-Authenticate' => 'Basic realm="LeProxy HTTP/SOCKS proxy"', 'Content-Type' => 'text/plain'),
                    'LeProxy HTTP/SOCKS proxy: Valid proxy authentication required'
                );
            }
        }

        if (strpos($request->getRequestTarget(), '://') !== false) {
            return $this->handlePlainRequest($request);
        }

        if ($request->getMethod() === 'CONNECT') {
            return $this->handleConnectRequest($request);
        }

        return new Response(
            405,
            array('Content-Type' => 'text/plain', 'Allow' => 'CONNECT'),
            'LeProxy HTTP/SOCKS proxy'
        );
    }

    /** @internal */
    public function handleConnectRequest(ServerRequestInterface $request)
    {
        // try to connect to given target host
        return $this->connector->connect($request->getRequestTarget())->then(
            function (ConnectionInterface $remote) {
                // connection established => forward data
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
    }

    /** @internal */
    public function handlePlainRequest(ServerRequestInterface $request)
    {
        $incoming = $request->withoutHeader('Host')
                            ->withoutHeader('Connection')
                            ->withoutHeader('Proxy-Authorization')
                            ->withoutHeader('Proxy-Connection');

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

    /** @internal */
    public function handlePac(ServerRequestInterface $request)
    {
        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
            return new Response(
                405,
                array('Accept' => 'GET')
            );
        }

        // use proxy URI from current request (and make sure to include port even if default)
        $uri = $request->getUri()->getHost() . ':' . ($request->getUri()->getPort() !== null ? $request->getUri()->getPort() : 80);

        return new Response(
            200,
            array('Content-Type' => 'application/x-ns-proxy-autoconfig'),
            'function FindProxyForURL(url, host) { return "PROXY ' . $uri . '"; }' . PHP_EOL
        );
    }
}
