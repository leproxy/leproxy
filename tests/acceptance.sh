#!/bin/bash

# test command line arguments
out=$(php leproxy.php --help) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(php leproxy.php -h) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(php leproxy.php --unknown 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(php leproxy.php --unknown 2>&1 || true) && echo "$out" | grep -q "see --help" && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

killall php 2>&- 1>&-s || true
php leproxy.php 127.0.0.1:8180 &
sleep 1

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 --location http://github.com 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5://127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks4a://127.0.0.1:8180 --location http://github.com  2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# unneeded authentication should work
out=$(curl -v --head --silent --fail --proxy http://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# invalid URIs should return error
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 https://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks://127.0.0.1:8180 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

# restart LeProxy with hosts and plain HTTP port blocked
killall php 2>&- 1>&-s || true
php leproxy.php 127.0.0.1:8180 --block=youtube.com --block=*.google.com --block=*:80 &
sleep 1

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 https://youtube.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks5h://127.0.0.1:8180 https://youtube.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 https://www.google.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks5h://127.0.0.1:8180 https://www.google.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://youtube.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks5h://127.0.0.1:8180 http://www.google.com 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://google.de 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks5h://127.0.0.1:8180 http://www.google.de 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 https://google.de 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5h://127.0.0.1:8180 https://google.de 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# restart LeProxy with authentication required
killall php 2>&- 1>&-s || true
php leproxy.php user:pass@127.0.0.1:8180 &
sleep 1

# authentication should work
out=$(curl -v --head --silent --fail --proxy http://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# invalid authentication should return error
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://reactphp.org 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks5://127.0.0.1:8180 http://reactphp.org 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

# start another LeProxy instance for HTTP proxy chaining / nesting
php leproxy.php 127.0.0.1:8181 http://user:pass@127.0.0.1:8180 &
sleep 1

# client does not need authentication because first chain passes to next via HTTP
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8181 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks://127.0.0.1:8181 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# start another LeProxy instance for SOCKS proxy chaining / nesting
php leproxy.php 127.0.0.1:8182 socks://user:pass@127.0.0.1:8180 &
sleep 1

# client does not need authentication because first chain passes to next via SOCKS
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8182 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks://127.0.0.1:8182 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

killall php 2>&- 1>&- || true
echo DONE
