<?php

use LeProxy\LeProxy\LeProxyServer;
use React\EventLoop\Factory;
use PHPUnit\Framework\TestCase;

class LeProxyServerTest extends TestCase
{
    /** @expectedException InvalidArgumentException */
    public function testInvalidListenUriThrows()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $proxy->listen('///', false);
    }

    public function testProxyDoesNotBlockTheLoopIfSocketIsClosed()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $socket = $proxy->listen('user:pass@127.0.0.1:8180', false);
        $socket->close();

        $loop->run();
    }

    public function testProxyDoesCreateSocketWithRandomPortForNullPort()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $socket = $proxy->listen('user:pass@127.0.0.1:0', false);

        $addr = $socket->getAddress();

        $this->assertStringStartsNotWith('127.0.0.1:', $addr);
        $this->assertStringEndsNotWith(':0', $addr);

        $socket->close();
    }
}
