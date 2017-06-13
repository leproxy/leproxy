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

LeProxy is now successfully running.
After configuring for example your browser settings you can surf via LeProxy!

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

## License

MIT-licensed
