<?php

use LeProxy\LeProxy\SourceConnector;

class SourceConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testConnnectAppendsSource()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80?source=http%3A%2F%2Fuser%3Apass%401.2.3.4%3A5060');

        $connector = new SourceConnector($connector, 'http://user:pass@1.2.3.4:5060');

        $connector->connect('example.com:80');
    }

    public function testConnnectAppendsSourceBehindExistingQuery()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80/?host=test&source=http%3A%2F%2Fuser%3Apass%401.2.3.4%3A5060');

        $connector = new SourceConnector($connector, 'http://user:pass@1.2.3.4:5060');

        $connector->connect('example.com:80/?host=test');
    }
}
