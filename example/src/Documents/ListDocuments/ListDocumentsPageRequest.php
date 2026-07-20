<?php

declare(strict_types=1);

namespace Example\Documents\ListDocuments;

use Example\Documents\DocumentKey;
use InvalidArgumentException;
use PHPThis\Http\InvalidRequest;

final readonly class ListDocumentsPageRequest
{
    /**
     * @param 'rank_asc'|'rank_desc' $order
     * @param list<string>|null $categories
     */
    private function __construct(
        public string $order,
        public ?array $categories,
        public ?int $cursorRank,
        public ?DocumentKey $cursorDocumentKey,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        if (count($query) > 3) {
            throw new InvalidRequest('List-documents query contains unsupported fields.');
        }

        foreach ($query as $field => $value) {
            if ($field !== 'order' && $field !== 'categories' && $field !== 'cursor') {
                throw new InvalidRequest('List-documents query contains unsupported fields.');
            }
        }

        $order = self::order($query);
        $categories = self::categories($query);
        [$cursorRank, $cursorDocumentKey] = self::cursor($query, $order);

        return new self($order, $categories, $cursorRank, $cursorDocumentKey);
    }

    /**
     * @param array<string, mixed> $query
     * @return 'rank_asc'|'rank_desc'
     */
    private static function order(array $query): string
    {
        if (!array_key_exists('order', $query)) {
            return 'rank_asc';
        }

        $order = $query['order'];

        if (!is_string($order) || ($order !== 'rank_asc' && $order !== 'rank_desc')) {
            throw new InvalidRequest('List-documents order is invalid.');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $query
     * @return list<string>|null
     */
    private static function categories(array $query): ?array
    {
        if (!array_key_exists('categories', $query)) {
            return null;
        }

        $submitted = $query['categories'];

        if (!is_array($submitted) || !array_is_list($submitted) || count($submitted) > 3) {
            throw new InvalidRequest('List-documents categories must be a list of at most three values.');
        }

        // Native PHP query parsing represents `?categories[]=` as [''].
        // Treat only that exact transport shape as an explicit empty selection.
        if ($submitted === ['']) {
            return [];
        }

        $categories = [];

        foreach ($submitted as $category) {
            if (
                !is_string($category)
                || $category === ''
                || strlen($category) > 64
                || preg_match('//u', $category) !== 1
                || preg_match('/[\x00-\x1F\x7F]/', $category) === 1
                || in_array($category, $categories, true)
            ) {
                throw new InvalidRequest('List-documents category is invalid.');
            }

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @param array<string, mixed> $query
     * @param 'rank_asc'|'rank_desc' $order
     * @return array{int|null, DocumentKey|null}
     */
    private static function cursor(array $query, string $order): array
    {
        if (!array_key_exists('cursor', $query)) {
            return [null, null];
        }

        $cursor = $query['cursor'];

        if (!is_string($cursor)) {
            throw new InvalidRequest('List-documents cursor is invalid.');
        }

        $parts = explode(':', $cursor);

        if (count($parts) !== 4) {
            throw new InvalidRequest('List-documents cursor is invalid.');
        }

        $version = $parts[0];
        $cursorOrder = $parts[1];
        $rank = $parts[2];
        $documentKey = $parts[3];

        if (
            $version !== 'v1'
            || ($cursorOrder !== 'rank_asc' && $cursorOrder !== 'rank_desc')
            || $cursorOrder !== $order
            || preg_match('/^(0|[1-9][0-9]*)$/D', $rank) !== 1
        ) {
            throw new InvalidRequest('List-documents cursor is invalid.');
        }

        $cursorRank = filter_var($rank, FILTER_VALIDATE_INT);

        if (!is_int($cursorRank) || $cursorRank < 0 || $cursorRank > 1_000_000) {
            throw new InvalidRequest('List-documents cursor is invalid.');
        }

        try {
            $cursorDocumentKey = DocumentKey::fromToken($documentKey);
        } catch (InvalidArgumentException) {
            throw new InvalidRequest('List-documents cursor is invalid.');
        }

        return [$cursorRank, $cursorDocumentKey];
    }
}
