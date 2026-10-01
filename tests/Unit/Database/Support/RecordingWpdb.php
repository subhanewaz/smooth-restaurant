<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Database\Support;

/**
 * Recording wpdb double for repository query tests.
 *
 * Captures insert/update/get_results calls and returns canned rows.
 * Complements FakeWpdb (prepare-only) so CRUD paths are testable without
 * WordPress.
 */
final class RecordingWpdb
{
    /**
     * Table prefix.
     *
     * @var string
     */
    public string $prefix = 'wp_';

    /**
     * Last inserted id, set by insert().
     *
     * @var int
     */
    public int $insert_id = 0;

    /**
     * Rows returned by every get_results() call.
     *
     * @var list<array<string, mixed>>
     */
    public array $results = [];

    /**
     * Recorded inserts.
     *
     * @var list<array{table: string, data: array<string, mixed>}>
     */
    public array $inserted = [];

    /**
     * Recorded updates.
     *
     * @var list<array{table: string, data: array<string, mixed>, where: array<string, mixed>}>
     */
    public array $updated = [];

    /**
     * Queries passed through prepare()/get_results().
     *
     * @var list<string>
     */
    public array $queries = [];

    /**
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): string
    {
        $this->queries[] = $query;

        $rendered = array_map(static fn ($value): string => (string) $value, $args);

        return $query . '|' . implode(',', $rendered);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_results(string $query): array
    {
        $this->queries[] = $query;

        return $this->results;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int|false
    {
        $this->inserted[] = ['table' => $table, 'data' => $data];
        $this->insert_id  = count($this->inserted);

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int|false
    {
        $this->updated[] = ['table' => $table, 'data' => $data, 'where' => $where];

        return 1;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}
