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

Once LeProxy is running, you can start using it with pretty much any client
software.
The below example assumes you want to use a web browser, but LeProxy can
actually be used with any client software that provides proxy support, such as
an email or IM client.

Most clients provide settings to manually configure a proxy server in their
settings/preferences dialogs.
You can simply set the details from the listening address as configured above:

* Protocol: HTTP or SOCKS
* Server: 127.0.0.1
* Port: 1080

> Note that these settings have to be adjusted to your actual network settings.
  If you fail to provide correct settings, no further connection will succeed.
  In this case, simply remove or disable these settings again.
  The same may apply if you're roaming in another network or the proxy server is
  temporarily not available.

Many clients (in particular web browsers and mobile phones) also support Proxy
Auto-Configuration (PAC) by specifying a PAC URL.
Using PAC is often beneficial because most clients will simply ignore the proxy
settings if the PAC URL can not be reached, such as when you're roaming in
another network or the proxy server is temporarily not available.
Simply use the URL to your LeProxy instance in the following format:

```
http://127.0.0.1:1080/pac
```

> Note that these settings have to be adjusted to your actual network settings.
  If you fail to provide correct settings, you may or may not be able to
  establish further connections, as most clients will simply ignore invalid
  settings.
  If your client disallows this, simply remove or disable these settings again.

## Web proxy

Once LeProxy is running, you can use the internal web proxy by entering:
`http://<address>:<port>/web`, so like in the examples before enter:
`http://127.0.0.1:1080/web`

The web proxy only excepts responses that or not bigger than 2MB. If you want
to access bigger sites or download a file, you should consider to configure
your [browser](#clients) to use LeProxy.

> Note that the web proxy is currently under development and several features
have to be implemented.
Currently the following features are not supoorted: CSS(style, images etc.), cookies, srcset/images, meta tags(http-equiv) and HTTPS.

## License

MIT-licensed
