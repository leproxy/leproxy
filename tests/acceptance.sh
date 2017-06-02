#!/bin/bash

killall php 2>&- 1>&-s || true
php leproxy.php socks://127.0.0.1:8180 &
sleep 1

out=$(curl -v --head --silent --fail --proxy socks5://127.0.0.1:8180 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy socks4a://127.0.0.1:8180 --location http://github.com  2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

out=$(curl -v --head --silent --fail --proxy socks://127.0.0.1:8180 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8180 http://reactphp.org 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

killall php 2>&- 1>&- || true
php leproxy.php http://127.0.0.1:8181 &
sleep 1

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8181 http://reactphp.org 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8181 --location http://github.com 2>&1) && echo OK || (echo "FAIL: $out" && exit 1) || exit 1

out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8181 http://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK
out=$(curl -v --head --silent --fail --proxy http://127.0.0.1:8181 https://test.invalid/test 2>&1) && echo "FAIL: $out" && exit 1 || echo OK

killall php 2>&- 1>&- || true

echo DONE
