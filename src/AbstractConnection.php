<?php

namespace BarretStorck\Rocket;

use Socket;
use Exception;
use InvalidArgumentException;

abstract class AbstractConnection
{
    const MAX_PORT_NUMBER = 65535;

    protected string $host;
    protected null|int $port;
    protected int $timeout = 0;
    protected int $domain = AF_UNIX;
    protected int $type = SOCK_STREAM;
    protected int $protocol = SOL_TCP;
    protected null|Socket $socket = null;

    /**
     *
     */
    public function __construct(string $host, null|int $port = null, int $timeout = 0, int $domain = AF_INET, int $type = SOCK_STREAM, int $protocol = SOL_TCP)
    {
        $this
            ->setDomain($domain) // Set the domain first because host and port rely on it
            ->setHost($host)
            ->setPort($port)
            ->setTimeout($timeout)
            ->setType($type)
            ->setProtocol($protocol)
            ->open();
    }

    /**
     * Automatically close the socket if the object is destructed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     *
     */
    protected function setHost(string $host): self
    {
        switch ($this->domain) {
            case AF_INET:
                // If the host is a domain name
                // then attempt to find it's IPv4 address.
                if (!static::isIpv4($host)) {
                    $host = gethostbyname($host);
                }

                if (!static::isIpv4($host)) {
                    throw new Exception('Unable to find IPv4 address for host.');
                }
                break;
            case AF_INET6:
                // If the host is a domain name
                // then attempt to find it's IPv6 address.
                if (!static::isIpv6($host)) {
                    $host = static::gethostbyname6($host);
                }

                if (!static::isIpv6($host)) {
                    throw new Exception('Unable to find IPv6 address for host.');
                }
                break;
            default:
                // This should only be possible if setHost() is called on an
                // instance where the constructor was skipped.
                throw new Exception('Unsupported domain for given host.');
                break;
        }

        $this->host = $host;
        return $this;
    }

    /**
     *
     */
    protected function setPort(null|int $port = null): self
    {
        // If using a unix socket
        // then port number is irrelevant so we set it to null.
        if ($this->domain === AF_UNIX) {
            $this->port = null;
            return $this;
        }

        // If the port number is out of range
        // then throw an exception.
        if (is_null($port) || $port < 1 || $port > static::MAX_PORT_NUMBER) {
            throw new InvalidArgumentException('Port number must be between 1 and ' . static::MAX_PORT_NUMBER . '.');
        }

        $this->port = $port;
        return $this;
    }

    /**
     *
     */
    public function setTimeout(int $timeout = 0): self
    {
        // Ensure a non-negative timeout period.
        $this->timeout = max(0, $timeout);
        return $this;
    }

    /**
     *
     */
    protected function setDomain(int $domain = AF_INET): self
    {
        $validDomains = [
            AF_INET,
            AF_INET6,
            AF_UNIX,
        ];

        if (!in_array($domain, $validDomains)) {
            throw new InvalidArgumentException('Unrecognized socket domain.');
        }

        if ($domain === AF_UNIX && PHP_OS_FAMILY === 'Windows') {
            throw new Exception('Windows does not support AF_UNIX domain.');
        }

        $this->domain = $domain;

        return $this;
    }

    /**
     *
     */
    protected function setType(int $type = SOCK_STREAM): self
    {
        $validTypes = [
            SOCK_STREAM,
            SOCK_DGRAM,
            SOCK_SEQPACKET,
            SOCK_RAW,
            SOCK_RDM,
        ];

        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Unrecognized socket type.');
        }

        $this->type = $type;
        return $this;
    }

    /**
     *
     */
    protected function setProtocol(int $protocol = SOL_TCP): self
    {
        $validProtocols = [
            SOL_TCP,
            SOL_UDP,
        ];

        if (!in_array($protocol, $validProtocols)) {
            throw new InvalidArgumentException('Unrecognized socket protocol.');
        }

        if ($this->domain === AF_UNIX) {
            $protocol = 0;
        }

        $this->protocol = $protocol;
        return $this;
    }

    /**
     * Determine if the given input is a valid IPv4 address.
     */
    protected static function isIpv4(string $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_IP, [
            'flags' => FILTER_FLAG_IPV4,
        ]) !== false;
    }

    /**
     * Determine if the given input is a valid IPv6 address.
     */
    protected static function isIpv6(string $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_IP, [
            'flags' => FILTER_FLAG_IPV6,
        ]) !== false;
    }

    /**
     * Fetches AAAA DNS records associated with the host and returns the first
     * one found. If none are found, then false is returned. This is meant to
     * match the gethostbyname() function, but for IPv6 addresses.
     */
    protected static function gethostbyname6(string $host): false|string
    {
        $dnsRecords = dns_get_record($host, DNS_AAAA);
        $ipv6 = $dnsRecords[0]['ipv6'] ?? false;
        return $ipv6;
    }

    /**
     * Safely close the socket connection if there is one.
     */
    public function close(): self
    {
        if (!($this->socket instanceof Socket)) {
            return $this;
        }

        @socket_shutdown($this->socket, 2);
        @socket_close($this->socket);

        $this->socket = null;
        return $this;
    }

    /**
     *
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     *
     */
    public function getPort(): null|int
    {
        return $this->port;
    }

    /**
     *
     */
    public function getDomain(): int
    {
        return $this->domain;
    }

    /**
     *
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     *
     */
    public function getProtocol(): int
    {
        return $this->protocol;
    }

    /**
     *
     */
    public function getUri(): string
    {
        $uri = '';

        switch ($this->protocol) {
            case SOL_TCP:
                $uri .= 'tcp://';
                break;
            case SOL_UDP:
                $uri .= 'udp://';
                break;
        }

        $uri .= $this->host;
        if ($this->port) {
            $uri .= ':' . ((string) $this->port);
        }

        return $uri;
    }

    /**
     *
     */
    abstract protected function open(): self;
}
