<?php

namespace BarretStorck\Rocket\Tests;

use BarretStorck\Rocket\Client;
use BarretStorck\Rocket\Server;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class ServerTest extends TestCase
{
    /**
     * 
     */
    public function testExternalClientConnects(): void
    {
        $host = '127.0.0.1';
        $port = 59821;
        $server = new Server($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 0,
            actual: $clientCount,
        );

        $externalSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $result = socket_connect($externalSocket, $host, $port);
        $this->assertTrue($result);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );

        socket_close($externalSocket);
    }

    /**
     * 
     */
    public function testInternalClientCanWrite(): void
    {
        $host = '127.0.0.1';
        $port = 59821;
        $server = new Server($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 0,
            actual: $clientCount,
        );

        $externalClient = new Client($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );
        
        $message = 'hello world';
        $internalClient = $server->getClients()[0];
        $internalClient->write($message);

        $response = $externalClient->read();
        $this->assertEquals(
            expected: $message,
            actual: $response,
        );
    }

    /**
     * 
     */
    public function testInternalClientCanRead(): void
    {
        $host = '127.0.0.1';
        $port = 59821;
        $server = new Server($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 0,
            actual: $clientCount,
        );

        $externalClient = new Client($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );
        
        $message = 'hello world';
        
        $externalClient->write($message);

        $internalClient = $server->getClients()[0];
        $response = $internalClient->read();
        $this->assertEquals(
            expected: $message,
            actual: $response,
        );
    }

    /**
     * 
     */
    public function testInternalClientIsAlive(): void
    {
        $host = '127.0.0.1';
        $port = 59821;
        $server = new Server($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 0,
            actual: $clientCount,
        );

        $externalClient = new Client($host, $port);

        $server->updateClients();
        $clientCount = count($server->getClients());

        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );
        
        $message = 'hello world';
        
        $externalClient->write($message);

        $internalClient = $server->getClients()[0];
        $result = $internalClient->isAlive();
        $this->assertTrue($result);

        // Close the external client connection
        unset($externalClient);

        $result = $internalClient->isAlive();
        $this->assertFalse($result);
    }
}
