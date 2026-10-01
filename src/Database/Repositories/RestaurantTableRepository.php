<?php

declare(strict_types=1);

namespace SmoothRestaurant\Database\Repositories;

use SmoothRestaurant\Contracts\RestaurantTableRepositoryInterface;
use SmoothRestaurant\Database\BaseRepository;

/**
 * Restaurant tables (floor plan) repository.
 *
 * Owns the smooth_tables row: label, seat count, soft-delete status, and the
 * operational QR state (free / seated / ordered / needs_bill). Schema is
 * dbDelta-managed; raw SQL only for keys dbDelta cannot express.
 */
class RestaurantTableRepository extends BaseRepository implements RestaurantTableRepositoryInterface
{
    /**
     * Active (non-archived) status marker.
     */
    private const STATUS_ACTIVE = 'active';

    /**
     * Archived (soft-deleted) status marker.
     */
    private const STATUS_ARCHIVED = 'archived';

    /**
     * Initial QR state for a newly created table.
     */
    private const STATE_FREE = 'free';

    protected function tableSuffix(): string
    {
        return 'smooth_tables';
    }

    /**
     * @return list<string>
     */
    protected function intColumns(): array
    {
        return ['id', 'seats'];
    }

    protected function columnDefinitions(): string
    {
        return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
            . "label varchar(64) NOT NULL DEFAULT '',\n"
            . "seats int(11) NOT NULL DEFAULT 2,\n"
            . "status varchar(32) NOT NULL DEFAULT 'active',\n"
            . "state varchar(32) NOT NULL DEFAULT 'free',\n"
            . 'PRIMARY KEY  (id)';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeTables(): array
    {
        $sql = $this->prepare(
            'SELECT * FROM `' . $this->getTable() . '` WHERE status = %s ORDER BY id ASC',
            self::STATUS_ACTIVE
        );

        return $this->mapRows($this->selectRows($sql));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = $this->prepare(
            'SELECT * FROM `' . $this->getTable() . '` WHERE id = %d AND status = %s LIMIT 1',
            $id,
            self::STATUS_ACTIVE
        );

        $rows = $this->mapRows($this->selectRows($sql));

        return $rows[0] ?? null;
    }

    public function create(string $label, int $seats): int
    {
        return $this->insertRow(
            [
                'label'  => $label,
                'seats'  => $seats,
                'state'  => self::STATE_FREE,
                'status' => self::STATUS_ACTIVE,
            ]
        );
    }

    public function updateState(int $id, string $state): bool
    {
        return $this->updateRows(
            ['state' => $state],
            ['id' => $id, 'status' => self::STATUS_ACTIVE]
        );
    }

    public function archive(int $id): bool
    {
        return $this->updateRows(
            ['status' => self::STATUS_ARCHIVED],
            ['id' => $id, 'status' => self::STATUS_ACTIVE]
        );
    }

    public function labelExists(string $label): bool
    {
        $sql = $this->prepare(
            'SELECT id FROM `' . $this->getTable() . '` WHERE label = %s AND status = %s LIMIT 1',
            $label,
            self::STATUS_ACTIVE
        );

        return [] !== $this->selectRows($sql);
    }
}
