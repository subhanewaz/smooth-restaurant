<?php

/**
 * Operational table state and its transition graph.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Domains\Tables;

/**
 * Operational state of a restaurant table.
 *
 * Distinct from the row lifecycle (`active`/`archived`): state tracks service
 * flow. The enum is the single source of truth for the transition graph so no
 * caller re-implements it:
 * - free → seated → ordered → needs_bill → free
 * - any state may reset to free
 * - needs_bill may step back to ordered
 */
enum TableState: string
{
    case Free      = 'free';
    case Seated    = 'seated';
    case Ordered   = 'ordered';
    case NeedsBill = 'needs_bill';

    /**
     * Every state, in graph order.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return \array_values(self::cases());
    }

    /**
     * State assigned to a freshly created table.
     *
     * @return self
     */
    public static function initial(): self
    {
        return self::Free;
    }

    /**
     * States reachable directly from this one.
     *
     * @return list<self>
     */
    public function nextStates(): array
    {
        return match ($this) {
            self::Free      => [self::Seated],
            self::Seated    => [self::Ordered, self::Free],
            self::Ordered   => [self::NeedsBill, self::Free],
            self::NeedsBill => [self::Ordered, self::Free],
        };
    }

    /**
     * Whether a direct move to the given state is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->nextStates(), true);
    }
}
