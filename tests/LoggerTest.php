<?php

use LeProxy\LeProxy\Logger;

class LoggerTest extends PHPUnit_Framework_TestCase
{
    public function testLogFailConnectionWithoutSourcePrintsErrorWithoutSource()
    {
        $this->expectOutputRegex('/\?\?\? failed to connect to example\.com:80 \(error\)\s+$/');

        $logger = new Logger();
        $logger->logFailConnection(null, 'example.com:80', 'error');
    }

    public function testLogFailConnectionWithSourcePrintsErrorWithSource()
    {
        $this->expectOutputRegex('/http:\/\/user@host failed to connect to example\.com:80 \(error\)\s+$/');

        $logger = new Logger();
        $logger->logFailConnection('http://user:pass@host:8080', 'example.com:80', 'error');
    }

    public function testLogConnectionWithoutSourcePrintsSuccessWithoutSource()
    {
        $this->expectOutputRegex('/\?\?\? connected to example\.com:80\s+$/');

        $logger = new Logger();
        $logger->logConnection(null, 'example.com:80', null);
    }

    public function testConnectSuccessWithSourcePrintsSuccessWithSource()
    {
        $this->expectOutputRegex('/http:\/\/user@host connected to example\.com:80\s+$/');

        $logger = new Logger();
        $logger->logConnection('http://user:pass@host:8080', 'example.com:80', null);
    }

    public function testLogConnectionWithoutRemoteIpPrintsSuccessWithDestinationAndIp()
    {
        $this->expectOutputRegex('/\?\?\? connected to example\.com:80 \(1\.2\.3\.4:5060\)\s+$/');

        $logger = new Logger();
        $logger->logConnection(null, 'example.com:80', '1.2.3.4:5060');
    }

    public function testConnectSuccessWithoutSameRemoteIpPrintsSuccessWithDestinationWithoutIp()
    {
        $this->expectOutputRegex('/\?\?\? connected to 1\.2\.3\.4:5060\s+$/');

        $logger = new Logger();
        $logger->logConnection(null, '1.2.3.4:5060', '1.2.3.4:5060');
    }
}
