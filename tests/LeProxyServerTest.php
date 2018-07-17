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

        $proxy->listen('@@@', false);
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

    public function testProxyDoesCreateSocketForUnixDomainSocketPath()
    {
        if (!in_array('unix', stream_get_transports())) {
            $this->markTestSkipped('System does not support unix:// scheme');
        }

        $loop = Factory::create();

        $proxy = new LeProxyServer($loop);

        $path = tempnam(sys_get_temp_dir(), 'sock');
        unlink($path);

        $socket = $proxy->listen('user:pass@' . $path, false);

        $addr = $socket->getAddress();

        $this->assertEquals('unix://' . $path, $addr);

        $socket->close();

        unlink($path);
    }
}
