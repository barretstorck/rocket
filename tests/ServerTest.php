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
    public function testConstructServer(): void
    {
        // Given
        // A socket server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // When we count the number of clients it has
        $server->updateClients();
        $clientCount = count($server->getClients());

        // Then we should expect zero clients
        $this->assertEquals(
            expected: 0,
            actual: $clientCount,
        );
    }

    /**
     *
     */
    public function testExternalClientConnects(): void
    {
        // Given
        // A socket server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a client connects to the server socket
        $externalSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $result = socket_connect($externalSocket, $host, $port);
        $this->assertTrue($result);
        $server->updateClients();

        // When we check the number of clients

        $clientCount = count($server->getClients());

        // Then we should have 1 client to correspond to the one that connected.
        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );

        socket_close($externalSocket);
    }

    /**
     *
     */
    public function testConstructClient(): void
    {
        // Given a Server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a Client object using the same host and port
        $externalClient = new Client($host, $port);
        $server->updateClients();

        // When checking the number of clients on the server
        $clientCount = count($server->getClients());

        // Then we should have 1 client
        $this->assertEquals(
            expected: 1,
            actual: $clientCount,
        );
    }

    /**
     *
     */
    public function testServerWriteToClient(): void
    {
        // Given a Server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a Client object using the same host and port
        $externalClient = new Client($host, $port);
        $server->updateClients();

        // When writing a message from the server to the client
        $message = 'hello world';
        $internalClient = $server->getClients()[0];
        $internalClient->write($message);

        // Then the client should be able to read the message
        $response = $externalClient->read();
        $this->assertEquals(
            expected: $message,
            actual: $response,
        );
    }

    /**
     *
     */
    public function testClientWriteToServer(): void
    {
        // Given a Server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a connected Client object
        $externalClient = new Client($host, $port);
        $server->updateClients();

        // When the Client writes to the Server
        $message = 'hello world';
        $externalClient->write($message);

        // Then the Server should be able to read the message
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
    public function testClientIsAlive(): void
    {
        // Given a Server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a connected Client object
        $externalClient = new Client($host, $port);
        $server->updateClients();

        // When the connection's liveness it checked
        $internalClient = $server->getClients()[0];
        $result = $internalClient->isAlive();

        // Then we should see it is alive.
        $this->assertTrue($result);
    }

    /**
     *
     */
    public function testClientIsNotAlive(): void
    {
        // Given a Server object
        $host = '127.0.0.1';
        $port = 1234;
        $server = new Server($host, $port);

        // And a connected Client object
        $externalClient = new Client($host, $port);
        $server->updateClients();

        // And the Client disconnects
        $externalClient->close();

        // When we check if the connection is alive
        $internalClient = $server->getClients()[0];
        $result = $internalClient->isAlive();

        // It should return false
        $this->assertFalse($result);
    }
}
