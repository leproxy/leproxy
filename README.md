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

**Table of contents**

* [Install](#install)
* [Usage](#usage)
* [Clients](#clients)
* [Development](#development)
* [License](#license)

> Note that this is an early beta version and that
  LeProxy is under active development.
  Many new features are going to be added in the future!

## Install

LeProxy requires only PHP.
*PHP 7+ is highly recommended*, but it runs on any system that uses PHP 5.4+ or
HHVM.
If you have not installed PHP already, on a recent Ubuntu/Debian system, simply run:

```bash
$ sudo apt-get install php7.0-cli
```

You can simply download the latest `leproxy-{version}.php` file from our
[releases page](https://github.com/leproxy/leproxy/releases).
The [latest release](https://github.com/leproxy/leproxy/releases/latest) can
always be downloaded like this:

```bash
$ curl -OL https://leproxy.org/leproxy-latest.php
```

Downloaded the `leproxy-{version}.php` file?
You did it!! Really simple, huh?

> LeProxy is distributed as a single PHP file that contains everything you need
  to run LeProxy.
  The below examples assume you have saved this file as `leproxy.php` locally,
  but you can use any name you want.
  If you're interested in the more technical details of this file, you may want
  to check out the [development instructions](#development) below.

## Usage

Once [installed](#install), you can start LeProxy by simply running:

```bash
$ php leproxy.php 
```

By default, LeProxy will listen on the public address `0.0.0.0:8080`. 
If you want to listen on another address, you can pass an explicit listening
address.
LeProxy will report an error if it fails to listen on the given address,
you may try another address or use port `0` to pick a random free port.
For example, if you do not want to allow accessing LeProxy from the outside and
only want to listen on the local interface:

```bash
$ php leproxy.php 127.0.0.1:8080
```

> The listening address MUST be in the form `ip:port` or just `ip` or `:port`,
  with the above defaults being applied (or you can use a Unix domain socket
  path).

Note that LeProxy runs in protected mode by default, so that it only forwards
requests from the local host and can not be abused as an open proxy.
If you have ensured only legit users can access your system, you can
pass the `--allow-unprotected` flag to forward requests from all hosts.
If you want to require the client to send username/password authentication
details, you can include this as part of the listening address:

```bash
$ php leproxy.php username:password@0.0.0.0:8080
```

> If the username or password contains special characters, make sure to use
  URL encoded values (percent-encoding) such as `p%40ss` for `p@ss`.

By default, LeProxy allows connections to every destination address as given in
each incoming proxy request.
If you want to block access to certain destination hosts and/or ports, you may
blacklist these by passing the `--block=<destination>` argument.
Any number of destination addresses can be given.
Each destination address can be in the form `host:port` or just `host` or
`:port` and `host` may contain the `*` wildcard to match anything.
Subdomains for each host will automatically be blocked.
For example, the following can be used to block access to `youtube.com` (and its
subdomains such as `www.youtube.com`) and port 80 on all hosts (standard
plaintext HTTP port):

```bash
$ php leproxy.php --block=youtube.com --block=*:80
```

> Note that the block list operates on the destination addresses as given in the
  incoming proxy request. Some [clients](#clients) use local DNS resolution and
  do not transmit hostnames, but only the resolved destination IP addresses
  (particularly common for the SOCKS protocol).
  Make sure to configure your client to use remote DNS resolution accordingly
  and/or also block access to relevant IP addresses.

As an alternative to listing each blocked destination as an individual command
line argument, you may also pass a path to a `hosts` file instead.
You can either create a hosts file mapping yourself if you only want to block
certain hosts or you may one of the many great hosts files.
For example, you can download a hosts file from https://github.com/StevenBlack/hosts
("hosts files from several well-curated sources like adaway.org, mvps.org,
malwaredomainlist.com, someonewhocares.org, and potentially others")
to use this as a very simple, yet effective adblocker.
Note that LeProxy will only block domains (and all its subdomains) that match
the IP `0.0.0.0` and ignore all other entries:

```bash
$ cat hosts.txt
127.0.0.1 localhost
0.0.0.0 ads.example.com
0.0.0.0 socialnetwork.example
…

$ php leproxy.php --block-hosts=hosts.txt
```

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
$ php leproxy.php --proxy=socks://user:pass@127.0.0.1:8080
```

> The upstream proxy server URI MUST contain a hostname or IP and SHOULD include
  a port unless the proxy happens to use default port `8080` (or you can use a
  Unix domain socket path).
  If no scheme is given, the `http://` scheme will be assumed.
  If no port is given, port `8080` will be assumed regardless of scheme.
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
$ php leproxy.php --proxy=127.1.1.1:8080 --proxy=127.2.2.2:8080 --proxy=127.3.3.3:8080
```

By default, LeProxy prints a log message for every connection attempt to the
console output (STDOUT) for debugging and analysis purposes.
For privacy reasons, it does not persist (store) these log messages on its own.
If you do not want LeProxy to log anything, you may also pass the `--no-log`
flag.
If you want to persist the log to a log file, you may use standard operating
system facilities such as `tee` to redirect the output to a file:

```bash
$ php leproxy.php | tee -a leproxy.log
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
* Server: 127.0.0.1 (or the public hostname or IP where LeProxy runs)
* Port: 8080

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
http://127.0.0.1:8080/pac
```

> Note that these settings have to be adjusted to your actual network settings.
  If you fail to provide correct settings, you may or may not be able to
  establish further connections, as most clients will simply ignore invalid
  settings.
  If your client disallows this, simply remove or disable these settings again.
  LeProxy's PAC file instructs your client to use LeProxy as an HTTP proxy for
  all public HTTP requests.
  This means that hostnames that resolve to IPs from your local network will
  still use a direct connection without going through a proxy.

## Development

LeProxy is an [open-source project](#license) and encourages everybody to
participate in its development.
You're interested in checking out how LeProxy works under the hood and/or want
to contribute to the development of LeProxy?
Then this section is for you!

The recommended way to install LeProxy is to clone (or download) this repository
and use [Composer](http://getcomposer.org) to download its dependencies.
Therefore you'll need PHP, git and curl installed.
For example, on a recent Ubuntu/debian system, simply run:

```bash
$ sudo apt-get install php7.0-cli git curl
$ git clone https://github.com/leproxy/leproxy.git
$ cd leproxy
$ curl -s https://getcomposer.org/installer | php
$ sudo mv composer.phar /usr/local/bin/composer
$ composer install
```

That's it already!
You should now be able to run the development version of LeProxy simply by
running the `leproxy.php` file like this:

```bash
$ php leproxy.php
```

See also [usage](#usage) for more details.

LeProxy uses a sophisticated test suite for functional tests and integration
tests.
To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

If you want to distribute LeProxy as a single standalone release file, you may
compile the project into a single file like this:

```bash
$ php compile.php
```

> Note that compiling will temporarily uninstall all development dependencies
  for distribution and then re-install the complete set of dependencies.
  This should only take a second or two if you've previously installed its
  dependencies already.
  The compile script optionally accepts the version number (`VERSION` env) and
  an output file name or will otherwise try to look up the last release tag,
  such as `leproxy-1.0.0.php`.

In addition to the above test suite, LeProxy uses a simple bash/curl-based
acceptance test setup which can also be used to check the resulting release
file:

```bash
$ ./tests/acceptance.sh
```

> Note that the acceptance tests will try to locate a `leproxy*.php` file in
  the project directory to run the tests against. You may optionally supply the
  output file name to test against.

Made some changes to your local development version?

Make sure to let the world know! :shipit:
We welcome PRs and would love to hear from you!

Happy hacking!

## License

LeProxy is an open source project released under the permissive
[MIT license](LICENSE).

LeProxy is standing on the shoulders of giants.
Building something like LeProxy probably wouldn't be possible if not for the
excellent open source projects that it builds on top of.
In particular, it uses [ReactPHP](http://reactphp.org/) for its fast,
event-driven architecture.

All of its dependencies are managed through Composer, see also the
[development section](#development) for more details.
If you're using the [development version](#development), you may run
`$ composer licenses --no-dev` to get a list of all runtime dependencies and their
respective licenses.
All these requirements are bundled into the single standalone release file.
