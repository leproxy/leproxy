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
use React\Stream\ReadableStreamInterface;
use React\Promise\Timer\TimeoutException;

class HttpProxyServer
{
    private $connector;
    private $client;
    private $auth = null;

    /**
     * Default headers to include if this is an origin response (i.e. not a forwarded response)
     *
     * @var array
     */
    private $headers = array(
        'Server' => 'LeProxy',
        'X-Powered-By' => ''
    );

    /**
     * Whether to allow unprotected access from outside or only allow local access
     *
     * @var bool
     */
    public $allowUnprotected = true;

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
        // assign client source address as attribute for connection logging
        $params = $request->getServerParams();
        if (isset($params['REMOTE_ADDR'], $params['REMOTE_PORT'])) {
            $request = $request->withAttribute('source', 'http://' . $params['REMOTE_ADDR'] . ':' . $params['REMOTE_PORT']);
        }

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

            // reject invalid authentication
            if (!$auth || !isset($this->auth[$auth[0]]) || $this->auth[$auth[0]] !== $auth[1]) {
                return new Response(
                    407,
                    array(
                        'Proxy-Authenticate' => 'Basic realm="LeProxy HTTP/SOCKS proxy"',
                        'Content-Type' => 'text/plain'
                    ) + $this->headers,
                    'LeProxy HTTP/SOCKS proxy: Valid proxy authentication required'
                );
            }

            // add username/password to source address attribute
            $source = $request->getAttribute('source');
            if ($source !== null) {
                $request = $request->withAttribute(
                    'source',
                    str_replace(
                        '://',
                        '://' . rawurlencode($auth[0]) . ':' . rawurlencode($auth[1]) . '@',
                        $source
                    )
                );
            }
        } elseif (!$this->allowUnprotected) {
            // reject requests not coming from 127.0.0.1/8 or IPv6 equivalent (protected mode)
            $params = $request->getServerParams();
            if (isset($params['REMOTE_ADDR']) && !ConnectorFactory::isIpLocal(trim($params['REMOTE_ADDR'], '[]'))) {
                return new Response(
                    403,
                    array(
                        'Content-Type' => 'text/plain'
                    ) + $this->headers,
                    'LeProxy HTTP/SOCKS proxy is running in protected mode and allows local access only'
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
            array(
                'Content-Type' => 'text/plain',
                'Allow' => 'CONNECT'
            ) + $this->headers,
            'LeProxy HTTP/SOCKS proxy'
        );
    }

    /** @internal */
    public function handleConnectRequest(ServerRequestInterface $request)
    {
        // add client source address for connection logging
        $uri = $request->getRequestTarget();
        $source = $request->getAttribute('source');
        if ($source !== null) {
            $uri .= '?source=' . rawurlencode($source);
        }

        // try to connect to given target host
        return $this->connector->connect($uri)->then(
            function (ConnectionInterface $remote) {
                // connection established => forward data
                return new Response(
                    200,
                    $this->headers,
                    $remote
                );
            },
            function (\Exception $e) {
                return new Response(
                    $this->getCode($e),
                    array(
                        'Content-Type' => 'text/plain'
                    ) + $this->headers,
                    'Unable to connect: ' . $this->getMessage($e)
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

        $headers = $incoming->getHeaders();
        if (!$request->hasHeader('User-Agent')) {
            $headers['User-Agent'] = array();
        }

        // add client source address for connection logging
        $source = $request->getAttribute('source');
        if ($source !== null) {
            $connector = new SourceConnector($this->connector, $source);

            // Move along, folks. Nothing to see here.
            // $this->client->connector = $connector;
            $ref = new \ReflectionObject($this->client);
            $ref = $ref->getProperty('connector');
            $ref->setAccessible(true);
            $ref->setValue($this->client, $connector);
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
            $response = new Response(
                $response->getCode(),
                $response->getHeaders(),
                $response,
                $response->getVersion(),
                $response->getReasonPhrase()
            );

            // Ensure we do not pass any default header values in downstream
            // response that are not present in upstream response by explicitly
            // using empty header values which will be removed automatically.
            foreach (array('X-Powered-By', 'Date') as $header) {
                if (!$response->hasHeader($header)) {
                    $response = $response->withHeader($header, '');
                }
            }

            $deferred->resolve($response);
        });

        $outgoing->on('error', function (Exception $e) use ($deferred) {
            $deferred->resolve(new Response(
                $this->getCode($e),
                array(
                    'Content-Type' => 'text/plain'
                ) + $this->headers,
                'Unable to request: ' . $this->getMessage($e)
            ));
        });

        $body = $incoming->getBody();
        if ($body instanceof ReadableStreamInterface) {
            $body->pipe($outgoing);
        } else {
            $outgoing->end((string)$body);
        }

        return $deferred->promise();
    }

    /** @internal */
    public function handlePac(ServerRequestInterface $request)
    {
        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
            return new Response(
                405,
                array(
                    'Accept' => 'GET'
                ) + $this->headers
            );
        }

        // use proxy URI from current request (and make sure to include port even if default)
        $uri = $request->getUri()->getHost() . ':' . ($request->getUri()->getPort() !== null ? $request->getUri()->getPort() : 80);

        return new Response(
            200,
            array(
                'Content-Type' => 'application/x-ns-proxy-autoconfig',
            ) + $this->headers,
            <<<EOF
function FindProxyForURL(url, host) {
    if (isPlainHostName(host) ||
        shExpMatch(host, "*.local") ||
        shExpMatch(host, "*.localhost") ||
        isInNet(dnsResolve(host), "10.0.0.0", "255.0.0.0") ||
        isInNet(dnsResolve(host), "172.16.0.0", "255.240.0.0") ||
        isInNet(dnsResolve(host), "192.168.0.0", "255.255.0.0") ||
        isInNet(dnsResolve(host), "127.0.0.0", "255.0.0.0")
    ) {
        return "DIRECT";
    }

    return "PROXY $uri";
}

EOF
);
    }

    /**
     * Returns an appropriate HTTP status code for the given Exception
     *
     * @param \Exception $e
     * @return int
     */
    private function getCode(\Exception $e)
    {
        if ($e->getCode() === ConnectorFactory::CODE_BLOCKED) {
            // Only map our block list to 403 (Forbidden)
            // Upstream proxy servers may return EACCESS (permission denied), but this
            // is a server-side issue and not to be reported as an auth issue to the client
            return 403;
        } elseif ($e instanceof TimeoutException) {
            // Only map our own TimeoutEception to 504 (Gateway Timeout)
            // Upstream proxy servers may return ETIMDOUT (timed out), but this
            // is a server-side issue and not to be reported as a timeout issue to the client
            return 504;
        }

        return 502; // Bad Gateway
    }

    /**
     * Returns the exception message and all its previous exceptions concatenated
     *
     * @param Exception $e
     * @return string
     */
    private function getMessage(Exception $e)
    {
        $message = '';
        while ($e !== null) {
            $message .= $e->getMessage() . "\n";
            $e = $e->getPrevious();
        }
        return $message;
    }
}
