<?php

/**
 * Restaurant table repository contract.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Contracts;

/**
 * Interface RestaurantTableRepositoryInterface
 *
 * Public surface of the floor-plan tables repository.
 */
interface RestaurantTableRepositoryInterface
{
    /**
     * Fully prefixed table name.
     *
     * @return string
     */
    public function getTable(): string;

    /**
     * dbDelta-first `CREATE TABLE` statement.
     *
     * @return string
     */
    public function schema(): string;

    /**
     * Map raw database rows to typed rows.
     *
     * @param list<array<string, mixed>> $rows Raw rows.
     * @return list<array<string, mixed>> Typed rows.
     */
    public function mapRows(array $rows): array;

    /**
     * Create (or update) the table via dbDelta.
     *
     * @return void
     */
    public function createTable(): void;

    /**
     * Active tables, oldest first.
     *
     * @return list<array<string, mixed>> Typed rows.
     */
    public function activeTables(): array;

    /**
     * One active table by id, or null when absent or archived.
     *
     * @param int $id Table id.
     * @return array<string, mixed>|null Typed row.
     */
    public function find(int $id): ?array;

    /**
     * Insert a new active table and return its id.
     *
     * @param string $label Display label.
     * @param int    $seats Seat count.
     * @param string $state Initial operational state, free by default.
     * @return int New table id.
     */
    public function create(string $label, int $seats, string $state = 'free'): int;

    /**
     * Set the operational state of an active table.
     *
     * @param int    $id    Table id.
     * @param string $state One of the TableState values.
     * @return bool Whether a row changed.
     */
    public function updateState(int $id, string $state): bool;

    /**
     * Soft-delete a table by flipping its status to archived.
     *
     * @param int $id Table id.
     * @return bool Whether a row changed.
     */
    public function archive(int $id): bool;

    /**
     * Whether an active table already uses the label.
     *
     * @param string $label Display label.
     * @return bool True when a matching active row exists.
     */
    public function labelExists(string $label): bool;
}
