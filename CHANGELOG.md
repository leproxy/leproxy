# Changelog

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

*   Feature: update Socket dependency to support hosts file on all platforms and
    update DNS dependency to fix Windows DNS timeout issues
    (#38 and #39 by @clue)

## 0.1.0 (2017-08-01)

* First tagged release :shipit:
