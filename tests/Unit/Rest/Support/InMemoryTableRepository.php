<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Rest\Support;

use SmoothRestaurant\Contracts\RestaurantTableRepositoryInterface;

/**
 * In-memory restaurant tables repository for controller tests.
 *
 * Implements the full contract so TablesController can be exercised without
 * WordPress or $wpdb.
 */
final class InMemoryTableRepository implements RestaurantTableRepositoryInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $rows = array();

    private int $nextId = 1;

    public function getTable(): string
    {
        return 'wp_smooth_tables';
    }

    public function schema(): string
    {
        return '';
    }

    /**
     * @param list<array<string, mixed>> $rows Raw rows.
     * @return list<array<string, mixed>>
     */
    public function mapRows(array $rows): array
    {
        return $rows;
    }

    public function createTable(): void
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeTables(): array
    {
        return \array_values(
            \array_filter(
                $this->rows,
                static fn (array $row): bool => 'active' === ( $row['status'] ?? '' )
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) $row['id'] === $id && 'active' === ( $row['status'] ?? '' )) {
                return $row;
            }
        }

        return null;
    }

    public function create(string $label, int $seats, string $state = 'free'): int
    {
        $id           = $this->nextId++;
        $this->rows[] = array(
            'id'     => $id,
            'label'  => $label,
            'seats'  => $seats,
            'state'  => $state,
            'status' => 'active',
        );

        return $id;
    }

    public function updateState(int $id, string $state): bool
    {
        foreach ($this->rows as $index => $row) {
            if ((int) $row['id'] === $id && 'active' === $row['status']) {
                $this->rows[ $index ]['state'] = $state;

                return true;
            }
        }

        return false;
    }

    public function archive(int $id): bool
    {
        foreach ($this->rows as $index => $row) {
            if ((int) $row['id'] === $id && 'active' === $row['status']) {
                $this->rows[ $index ]['status'] = 'archived';

                return true;
            }
        }

        return false;
    }

    public function labelExists(string $label): bool
    {
        foreach ($this->rows as $row) {
            if ('active' === ( $row['status'] ?? '' ) && $row['label'] === $label) {
                return true;
            }
        }

        return false;
    }
}
