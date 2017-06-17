#!/bin/bash

killall php 2>&- 1>&-s || true
php leproxy.php 127.0.0.1:8180 &
sleep 1

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 --location http://github.com 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5://127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks4a://127.0.0.1:8180 --location http://github.com  2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# ensure PAC URL returns valid file for direct access
out=$(curl -v --silent --fail http://127.0.0.1:8180/pac 2>&1) && echo "$out" | grep -q PROXY && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --silent --fail -x DELETE http://127.0.0.1:8180/pac 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --silent --fail --proxy http://127.0.0.1:8180 http://test.invalid/pac 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

# unneeded authentication should work
out=$(curl -v --head --silent --fail --proxy http://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks5://user:pass@127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

# invalid URIs should return error
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 https://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy socks://127.0.0.1:8180 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

# manual protocol tests (direct and through HTTP CONNECT to self)
out=$(bash -c "echo -n -e \"GET /pac HTTP/1.1\r\n\r\n\"" | nc localhost 8180 2>&1) && echo "$out" | grep -q "200 OK" && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(bash -c "echo -n -e \"CONNECT 127.0.0.1:8180 HTTP/1.1\r\n\r\n\";sleep 1; echo -n -e \"GET /pac HTTP/1.1\r\n\r\n\"" | nc localhost 8180 2>&1) && echo "$out" | grep -q "200 OK" && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

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
