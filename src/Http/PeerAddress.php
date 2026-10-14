<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class PeerAddress
{
    private function __construct(public string $address)
    {
    }

    public static function fromString(string $address): self
    {
        if (strlen($address) > 45 || filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Peer address must be a bare IPv4 or IPv6 address of at most 45 bytes.');
        }
        return new self($address);
    }
}
