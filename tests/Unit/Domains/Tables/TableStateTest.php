<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Domains\Tables;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Domains\Tables\TableState;

/**
 * Unit tests for the TableState enum.
 *
 * Locks the operational state graph: forward flow free → seated → ordered →
 * needs_bill → free, an any-state reset to free, and the needs_bill → ordered
 * step back. The enum is the single source of valid next states.
 */
final class TableStateTest extends TestCase
{
    /**
     * @return array<string, array{TableState, list<TableState>}>
     */
    public static function nextStatesProvider(): array
    {
        return [
            'free flows to seated' => [TableState::Free, [TableState::Seated]],
            'seated flows forward or resets' => [TableState::Seated, [TableState::Ordered, TableState::Free]],
            'ordered flows forward or resets' => [TableState::Ordered, [TableState::NeedsBill, TableState::Free]],
            'needs bill steps back or resets' => [TableState::NeedsBill, [TableState::Ordered, TableState::Free]],
        ];
    }

    /**
     * @param list<TableState> $expected
     */
    #[DataProvider('nextStatesProvider')]
    public function test_next_states_match_the_graph(TableState $state, array $expected): void
    {
        $this->assertSame($expected, $state->nextStates());
    }

    /**
     * @return array<string, array{TableState, TableState, bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            'free to seated allowed' => [TableState::Free, TableState::Seated, true],
            'free to ordered skipped' => [TableState::Free, TableState::Ordered, false],
            'seated to ordered allowed' => [TableState::Seated, TableState::Ordered, true],
            'seated resets to free' => [TableState::Seated, TableState::Free, true],
            'ordered to needs bill allowed' => [TableState::Ordered, TableState::NeedsBill, true],
            'ordered to seated rejected' => [TableState::Ordered, TableState::Seated, false],
            'ordered resets to free' => [TableState::Ordered, TableState::Free, true],
            'needs bill to ordered allowed' => [TableState::NeedsBill, TableState::Ordered, true],
            'needs bill resets to free' => [TableState::NeedsBill, TableState::Free, true],
            'needs bill to seated rejected' => [TableState::NeedsBill, TableState::Seated, false],
            'same state is not a move' => [TableState::Free, TableState::Free, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function test_can_transition_to_follows_the_graph(TableState $from, TableState $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public function test_initial_state_is_free(): void
    {
        $this->assertSame(TableState::Free, TableState::initial());
    }

    public function test_all_lists_every_state(): void
    {
        $this->assertSame(
            [
                TableState::Free,
                TableState::Seated,
                TableState::Ordered,
                TableState::NeedsBill,
            ],
            TableState::all()
        );
    }
}
