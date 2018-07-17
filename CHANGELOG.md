# Changelog

## 0.2.2 (2018-07-17)

*   Feature: Support listening on Unix domain socket (UDS) paths and
    support proxy forwarding/chaining via Unix domain socket (UDS) paths.
    (#51 by @clue)

    LeProxy now supports listening on Unix domain socket (UDS) paths and
    proxy forwarding/chaining via Unix domain socket (UDS) paths,
    both of which are considered advanced usage:

    ```bash
    $ php leproxy.php ./proxy.socket

    $ php.leproxy.php :8080 --proxy http+unix://./proxy.socket
    ```

*   Feature: Update HTTP dependencies and reject chunked requests and
    update ReactPHP to stable LTS releases.
    (#49 and #50 by @clue)

## 0.2.1 (2018-03-09)

*   Feature: Update Socket and DNS dependency to support loading system default
    DNS config on all supported platforms.
    (`/etc/resolv.conf` on Unix/Linux/Mac/Docker/WSL and WMIC on Windows)
    (#45 by @clue)

    This means that connecting to hosts that are managed by a local DNS server,
    such as a corporate DNS server or when using Docker containers, will now
    work as expected across all platforms with no changes required.

*   Fix: Update HTTP and HttpClient dependencies to include a number of
    improvements for HTTP handling (support multiple response cookies, larger
    request headers and ignore corrupt response Transfer-Encoding).
    (#46 by @clue)

*   Reduce package size by updating HttpClient dependency and removing unneeded deps.
    (#47 by @clue)

*   Improve test suite by adding forward compatibility with updated
    react/promise-stream and fix Travis builds by skipping all IPv6 tests.
    (#42 by @WyriHaximus and #44 by @clue)

## 0.2.0 (2017-09-01)

*   Feature: Add `--block=<target>` argument to blacklist destination addresses and
    add `--block-hosts=<path> argument` to block multiple hosts and
    use proper HTTP/SOCKS status codes and improve error reporting and analysis
    (#24, #40 and #41 by @clue)

    For example, the following command allows you to block all plaintext HTTP
    requests and use LeProxy as a simple, yet effective adblocker:

    ```bash
    $ php leproxy.php --block=:80 --block-hosts=hosts-ads.txt
    ```

*   Feature: Validate all arguments through commander instead of throwing exception
    (#37 by @clue)

*   Feature: Update Socket dependency to support hosts file on all platforms and
    update DNS dependency to fix Windows DNS timeout issues
    (#38 and #39 by @clue)

## 0.1.0 (2017-08-01)

* First tagged release :shipit:
