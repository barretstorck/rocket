<?php

namespace BarretStorck\Rocket;

use Socket;
use Exception;
use InvalidArgumentException;

final class Client extends AbstractConnection
{
    const BUFFER_SIZE = 8192; // 8 KBs

    /**
     *
     */
    public function __construct(string $host, null|int $port = null, int $timeout = 0, int $domain = AF_INET, int $type = SOCK_STREAM, int $protocol = SOL_TCP, null|Socket $socket = null)
    {
        $this->socket = $socket;
        parent::__construct($host, $port, $timeout, $domain, $type, $protocol);
    }



    /**
     *
     */
    protected function open(): self
    {
        if ($this->socket instanceof Socket) {
            return $this;
        }

        $this->socket = socket_create($this->domain, $this->type, $this->protocol);
        socket_connect($this->socket, $this->host, $this->port);

        return $this;
    }

    /**
     * Checks if the socket is alive or not based on if it is possible to check
     * the socket connection for incoming data. This will only peek for 1 byte
     * so no data is removed from the buffer.
     */
    public function isAlive(): bool
    {
        if (!($this->socket instanceof Socket)) {
            return false;
        }

        // If socket_recv returns false while attempting to read data
        // then the socket can be considered disconnected. If it returns 0 then
        // that means an orderly shutdown has occurred.
        $result = socket_recv($this->socket, $data, 1, MSG_PEEK);
        return $result !== false && $result !== 0;
    }

    /**
     * Checks if the socket has any data waiting to be recieved.
     */
    public function hasData(): bool
    {
        if (!($this->socket instanceof Socket)) {
            return false;
        }

        $buffer = socket_recv($this->socket, $data, 1, MSG_PEEK | MSG_DONTWAIT);
        return $buffer !== false && strlen($buffer) > 0;
    }

    /**
     *
     */
    public function read(): string
    {
        if (!($this->socket instanceof Socket)) {
            throw new Exception('Socket is not available.');
        }

        $started = microtime(true);

        socket_recv($this->socket, $data, static::BUFFER_SIZE, MSG_DONTWAIT);

        while (is_null($data) && $this->timeout >= (microtime(true) - $started)) {
            usleep(100);
            socket_recv($this->socket, $r, static::BUFFER_SIZE, MSG_DONTWAIT);
        }

        return $data ?? '';
    }

    /**
     *
     */
    public function write($data): int
    {
        if (!($this->socket instanceof Socket)) {
            throw new Exception('Socket is not available.');
        }

        if (is_resource($data)) {
            $byteCount = 0;
            while ($chunk = fread($data, static::BUFFER_SIZE)) {
                $byteCount += socket_write($this->socket, $chunk);
            }
            return $byteCount;
        } elseif (is_string($data)) {
            return socket_write($this->socket, $data);
        }

        throw new InvalidArgumentException('write($data) requires $data to be string or resource.');
    }
}
