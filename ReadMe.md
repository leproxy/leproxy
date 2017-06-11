# LeProxy
Stable Proxy Server for security reasons/anonymous surfing/everyone
## Features
`LeProxy` is a Proxy server which accepts both HTTP and SOCKS on a
single listening port and if needed, proxy authentication.
Note that this is just a very early version and that `LeProxy` is
under high development. Many new features are going to be added in the future!
## Usage
Once [installed](#install), you can start `LeProxy` on the default adress `localhost:9050` by running:
```bash
$ php leproxy.php 
```
If you want to listen on another address, you can supply an explicit
listen address like this:
```bash
# start LeProxy on port 9051 instead
$ php leproxy.php 9051

# explicitly listen on the given interface
$ php leproxy.php 192.168.1.2:9050

# listen on all interfaces (allow access to LeProxy from the outside)
$ php leproxy.php *:9050

# require client to send the given authentication information
$ php leproxy.php username:password@localhost:9051
```
`LeProxy` is now successfully running. After configuring for example your
browser settings you can surf via `LeProxy`!
## Install
The recommend way to install LeProxy is to clone (or download) this repository
and use [composer](http://getcomposer.org) to download its dependencies. Therefore
you'll need PHP, git and curl installed:
```bash
$ sudo apt-get install php7.0 php7.0-cli git curl
$ git clone https://github.com/leproxy/leproxy.git
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```
You did it!! Really simple, huh?
## License
MIT-licensed
