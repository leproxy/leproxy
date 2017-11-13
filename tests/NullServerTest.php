<?php

use LeProxy\LeProxy\NullServer;
use PHPUnit\Framework\TestCase;

class NullServerTest extends TestCase
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
