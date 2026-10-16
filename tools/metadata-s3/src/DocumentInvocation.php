<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

final readonly class DocumentInvocation
{
    private function __construct(public string $command, public DocumentId|PacketId $id, public DocumentOwner $owner) {}

    /** @param list<string> $arguments */
    public static function fromArguments(array $arguments): self
    {
        if ($arguments === []) { throw new \InvalidArgumentException('Missing command.'); }
        $command = $arguments[0];
        if (!in_array($command, ['reconcile-one', 'expire-one', 'reconcile-packet', 'purge-packet'], true)) { throw new UnknownDocumentCommand(); }
        if (count($arguments) !== 3 || preg_match('/\A[1-9][0-9]{0,18}\z/', $arguments[2]) !== 1) {
            throw new \InvalidArgumentException('Invalid arguments.');
        }
        $owner = filter_var($arguments[2], FILTER_VALIDATE_INT);
        if (!is_int($owner)) { throw new \InvalidArgumentException('Invalid owner.'); }
        return new self($command, in_array($command, ['reconcile-packet', 'purge-packet'], true)
            ? new PacketId($arguments[1]) : new DocumentId($arguments[1]), new DocumentOwner($owner));
    }
}
