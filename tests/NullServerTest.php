<?php

use LeProxy\LeProxy\NullServer;

class NullServerTest extends PHPUnit_Framework_TestCase
{
    public function testDoesNothing()
    {
        $server = new NullServer();

        $this->assertNull($server->getAddress());

        $server->pause();
        $server->resume();
        $server->close();
    }
}
