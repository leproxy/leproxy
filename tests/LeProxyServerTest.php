<?php

use LeProxy\LeProxy\LeProxyServer;
use React\EventLoop\Factory;

class LeProxyServerTest extends PHPUnit_Framework_TestCase
{
    /** @expectedException InvalidArgumentException */
    public function testInvalidListenUriThrows()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $proxy->listen('///');
    }

    public function testProxyDoesNotBlockTheLoopIfSocketIsClosed()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $socket = $proxy->listen('user:pass@127.0.0.1:8180');
        $socket->close();

        $loop->run();
    }

    public function testProxyDoesCreateSocketWithRandomPortForNullPort()
    {
        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $socket = $proxy->listen('user:pass@127.0.0.1:0');

        $addr = $socket->getAddress();

        $this->assertStringStartsNotWith('127.0.0.1:', $addr);
        $this->assertStringEndsNotWith(':0', $addr);

        $socket->close();
    }
}
