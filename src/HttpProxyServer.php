<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\HttpClient\Client as ReactHttpClient;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ServerInterface;

class HttpProxyServer
{
    private $connector;
    private $client;
    private $auth = null;
    public $webProxy;

    public function __construct(LoopInterface $loop, ServerInterface $socket, ConnectorInterface $connector, ReactHttpClient $client = null)
    {
        if ($client === null) {
            $client = new ReactHttpClient($loop, $connector);
        }

        $this->connector = $connector;
        $this->client = new HttpClient($client);

        $that = $this;
        $socket->on('connection', function (ConnectionInterface $connection) use ($that) {
            $serverAddress = str_replace('tcp://', 'http://', $connection->getLocalAddress());
            $that->webProxy = new WebProxy($this->client, $serverAddress);
        });

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
        if ($direct && $request->getUri()->getPath() === '/web') {
            return $this->webProxy->handleRequest($request);
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
        $request = $request->withoutHeader('Host')
                           ->withoutHeader('Connection')
                           ->withoutHeader('Proxy-Authorization')
                           ->withoutHeader('Proxy-Connection');

        return $this->client->send($request)->then(null, function (\Exception $e) {
            $message = '';
            while ($e !== null) {
                $message .= $e->getMessage() . "\n";
                $e = $e->getPrevious();
            }

            return new Response(
                502,
                array('Content-Type' => 'text/plain'),
                'Unable to request: ' . $message
            );
        });

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
