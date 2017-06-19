<?php

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
}
