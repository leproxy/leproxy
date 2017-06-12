# LeProxy

LeProxy is the HTTP/SOCKS proxy server for everybody!

LeProxy is designed for anonymous surfing, improved security and privacy plus
circumventing geoblocking.
It allows you to enjoy the web like it's meant to work and access your favorite
online video platform without annoying country blocks while traveling.

LeProxy is a powerful, lightweight, fast and simple to use proxy server that you
can host on your own server or PC at home and then access from anywhere.
It supports optional authentication so you can share a server instance with your
family and friends without having to worry about third parties.
It provides compatibility with a large number of clients and services by
accepting both common HTTP and SOCKS proxy protocols on a single listening port.

> Note that this is a very early alpha version and that
  LeProxy is under active development.
  Many new features are going to be added in the future!

## Install

LeProxy requires only PHP.
*PHP 7+ is highly recommended*, but it runs on any system that uses PHP 5.4+ or
HHVM.

If you're already familiar with [Composer](http://getcomposer.org), here's the
quick install quide.
Simply download LeProxy and run:

```bash
$ composer install --no-dev
```

You did it!! Really simple, huh?

Anything unclear? Here's the full step-by-step guide:
The recommend way to install LeProxy is to clone (or download) this repository
and use [Composer](http://getcomposer.org) to download its dependencies.
Therefore you'll need PHP, git and curl installed.
For example, on a recent Ubuntu system, simply run:

```bash
$ sudo apt-get install php7.0 php7.0-cli git curl
$ git clone https://github.com/leproxy/leproxy.git
$ cd leproxy
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install --no-dev
```

## Usage

Once [installed](#install), you can start LeProxy by simply running:

```bash
$ php leproxy.php 
```

By default, LeProxy will listen on the address `127.0.0.1:1080`. 
If you want to listen on another address, you can pass an explicit listening
address.
For example, if you want to listen on all interfaces and allow access to LeProxy
from the outside:

```bash
$ php leproxy.php 0.0.0.0:1080
```

Note that LeProxy does not require authentication by default,
so the above should be used with care.
If you want to require the client to send username/password authentication
details, you can include this as part of the listening address:

```bash
$ php leproxy.php username:password@0.0.0.0:1080
```

> If the username or password contains special characters, make sure to use
  URL encoded values (percent-encoding) such as `p%40ss` for `p@ss`.

By default, Leproxy creates a direct connection to the destination address for
each incoming proxy request.
In this mode, the destination doesn't see the original client address, but only
the address of your LeProxy instance.
If you want a higher level degree of anonymity, you can use *proxy forwarding*,
where the connection will be tunneled through another upstream proxy server.
This may also be useful if your upstream proxy address changes regularly (such
as when using public proxies), but you do not want to reconfigure your client
every time or if your upstream proxy requires a feature that your client does
not support, such as requiring authentication or a different proxy protocol.
You can simply pass your upstream proxy server address as another URL parameter
after the listening address like this:

```bash
$ php leproxy.php 0.0.0.0:1080 socks://user:pass@127.0.0.1:8080
```

> The upstream proxy server URI MUST contain a hostname or IP and SHOULD include
  a port unless it is the standard port for this proxy scheme.
  If no scheme is given, the `http://` scheme will be assumed.
  The `http://` scheme uses default port 80, all `socks[5|4|4a]://` schemes
  default to port 1080.
  The `http://` and `socks[5]://` schemes support optional username/password
  authentication as in the above example.

By appending additional upstream proxy servers, this can effectively be turned
into *proxy chaining*, where each incoming proxy request will be forwarded
through the chain of all upstream proxy servers from left to right.
This comes at the price of increased latency, but may provide a higher level
degree of anonymity, as each proxy server in the chain only sees its direct
communication partners and the destination only sees the last proxy server in
the chain:

```bash
$ php leproxy.php 0.0.0.0:1080 127.1.1.1:1080 127.2.2.2:1080 127.3.3.3:1080
```

## Clients

Once LeProxy is running, you can start using it with pretty much any client software.
For this, you'll need to configure your browser settings to use this proxy server
with the listening address as configured above:

* Protocol: HTTP or SOCKS
* Server: 127.0.0.1
* Port: 1080

## License

MIT-licensed
