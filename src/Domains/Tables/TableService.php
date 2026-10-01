<?php

/**
 * Pure table-management domain service.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Domains\Tables;

use SmoothRestaurant\Exceptions\TableException;

/**
 * Class TableService
 *
 * Normalizes table labels and state strings, validates moves against the
 * TableState graph, and builds the display-only QR menu URL. Stays free of
 * WordPress and database access so providers can bind it in register().
 */
final class TableService
{
    /**
     * Maximum label length, matching the `varchar(64)` column.
     */
    private const MAX_LABEL_LENGTH = 64;

    /**
     * Parse a raw state string into a TableState.
     *
     * @throws TableException When the value is not a known state.
     */
    public function state(string $value): TableState
    {
        $state = TableState::tryFrom(\strtolower(\trim($value)));
        if (null === $state) {
            throw new TableException(\sprintf('Unknown table state "%s".', $value));
        }

        return $state;
    }

    /**
     * Validate a move between two raw state strings.
     *
     * @throws TableException When either state is unknown or the move is not
     *                        allowed by the state graph.
     */
    public function move(string $from, string $to): TableState
    {
        $origin = $this->state($from);
        $target = $this->state($to);
        if (! $origin->canTransitionTo($target)) {
            throw new TableException(
                \sprintf('Cannot move a table from %s to %s.', $origin->value, $target->value)
            );
        }

        return $target;
    }

    /**
     * Normalize and validate a table label.
     *
     * Strips markup, trims, and collapses internal whitespace, then enforces a
     * 1–64 character length.
     *
     * @throws TableException When the cleaned label is empty or too long.
     */
    public function normalizeLabel(string $label): string
    {
        $cleaned = \trim((string) \preg_replace('/\s+/u', ' ', \strip_tags($label)));
        if ('' === $cleaned) {
            throw new TableException('Table label must not be empty.');
        }

        if (\mb_strlen($cleaned, 'UTF-8') > self::MAX_LABEL_LENGTH) {
            throw new TableException(
                \sprintf('Table label must be %d characters or fewer.', self::MAX_LABEL_LENGTH)
            );
        }

        return $cleaned;
    }

    /**
     * Build the QR menu URL for a table label.
     *
     * The `?table=` value is display-only; the menu owner decides whether to
     * consume it.
     *
     * @throws TableException When the label is invalid.
     */
    public function menuUrl(string $baseUrl, string $label): string
    {
        $normalized = $this->normalizeLabel($label);
        $separator  = \str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'table=' . \rawurlencode($normalized);
    }
}
