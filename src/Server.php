<?php

namespace BarretStorck\Rocket;

use Socket;
use Exception;

final class Server extends AbstractConnection
{
    private array $clients = [];

    /**
     *
     */
    protected function open(): self
    {
        // If we already have an active socket
        // then there is nothing to do so we immediately return.
        if ($this->socket instanceof Socket) {
            return $this;
        }

        // Create a new socket connection.
        $this->socket = socket_create($this->domain, $this->type, $this->protocol);

        // Bind the socket to our host and port
        socket_bind($this->socket, $this->host, $this->port);

        // Listen for client connections
        socket_listen($this->socket);

        // Prevent the socket from blocking. This means that checking for
        // recieved data will not cause PHP to stop and wait for a client to
        // send something.
        socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, ['l_linger' => 0, 'l_onoff' => 1]);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1);
        socket_set_nonblock($this->socket);

        return $this;
    }

    /**
     *
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Remove disconnected clients from the list of active clients.
     */
    public function closeDisconnectedClients(): self
    {
        $this->clients = array_values(array_filter($this->clients, function ($client) {
            return $client->isAlive();
        }));

        return $this;
    }

    /**
     * Attempt to get a new Client socket connection if any clients are
     * attempting to connect to our Server.
     */
    public function connectClient(): null|Client
    {
        // Attempt to accept a client socket connection.
        $clientSocket = socket_accept($this->socket);

        // If we didn't get a socket client connection
        // then no clients were attempting to connect, so we return null.
        if (!$clientSocket) {
            return null;
        }

        // Get the client socket's host and port information.
        socket_getpeername($clientSocket, $host, $port);

        // Set it to not block.
        socket_set_nonblock($clientSocket);

        // Create a new socket Client object to manage the client connection.
        $client = new Client(
            host: $host,
            port: $port,
            domain: $this->domain,
            type: $this->type,
            protocol: $this->protocol,
            timeout: $this->timeout,
            socket: $clientSocket,
        );

        // Add it to our list of connected clients.
        $this->clients[] = $client;

        return $client;
    }

    /**
     *
     */
    public function connectClients(): self
    {
        if (!($this->socket instanceof Socket)) {
            throw new Exception('Socket is not available.');
        }

        // As long as there are clients attempting connections to the server
        // continue to connect them and add them to the client list.
        while ($this->connectClient()) {
        }

        return $this;
    }

    /**
     *
     */
    public function updateClients(): self
    {
        return $this
            ->closeDisconnectedClients()
            ->connectClients();
    }

    /**
     *
     */
    public function writeAll($data): self
    {
        $this->updateClients();

        if (is_string($data)) {
            foreach ($this->clients as $client) {
                $client->write($data);
            }
        } elseif (is_resource($data)) {
            $offset = ftell($data);
            foreach ($this->clients as $client) {
                fseek($data, $offset);
                $client->write($data);
            }
        }

        return $this;
    }
}
