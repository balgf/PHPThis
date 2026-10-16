<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

/** One reviewed generated fixture, not a parser for arbitrary WAV input. */
final readonly class TestToneBytes
{
    private function __construct(public ExportBytes $bytes) {}

    public static function fromProducerOutput(string $bytes): self
    {
        // Independently reproduced by the Python sine calculation and WAV writer in the process proof.
        if (strlen($bytes) !== 32_044
            || !hash_equals('7d56186cf1e74ae7a6413d3c4975188ff6464025df047f81326a40dbc83087b0', hash('sha256', $bytes))) {
            throw new ExportUnavailable('Test tone unavailable.');
        }
        return new self(ExportBytes::fromBytes($bytes));
    }
}
