<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Domains\Tables;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Domains\Tables\TableService;
use SmoothRestaurant\Domains\Tables\TableState;
use SmoothRestaurant\Exceptions\TableException;

/**
 * Unit tests for the TableService domain service.
 *
 * Covers state-string normalization, the guarded transition API, label
 * normalization, and the pure menu-URL builder. No WordPress or database
 * access: the service stays inside src/Domains/.
 */
final class TableServiceTest extends TestCase
{
    public function test_state_parses_known_values(): void
    {
        $service = new TableService();

        $this->assertSame(TableState::Free, $service->state('free'));
        $this->assertSame(TableState::Seated, $service->state('seated'));
        $this->assertSame(TableState::Ordered, $service->state('ordered'));
        $this->assertSame(TableState::NeedsBill, $service->state('needs_bill'));
    }

    public function test_state_trims_and_lowercases(): void
    {
        $this->assertSame(TableState::Ordered, (new TableService())->state('  Ordered '));
    }

    public function test_state_rejects_unknown_value(): void
    {
        $this->expectException(TableException::class);

        (new TableService())->state('dirty');
    }

    public function test_move_accepts_a_legal_transition(): void
    {
        $this->assertSame(
            TableState::NeedsBill,
            (new TableService())->move('ordered', 'needs_bill')
        );
    }

    public function test_move_rejects_an_illegal_transition(): void
    {
        $this->expectException(TableException::class);

        (new TableService())->move('ordered', 'seated');
    }

    public function test_move_rejects_an_unknown_state(): void
    {
        $this->expectException(TableException::class);

        (new TableService())->move('free', 'bogus');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'trims and collapses whitespace' => ['  Table   1  ', 'Table 1'],
            'strips tags' => ['<b>Patio</b>', 'Patio'],
            'collapses tabs and newlines' => ["Bar\t\t2\n", 'Bar 2'],
            'keeps inner text of stripped tags' => ['<em>Deck &amp; Bar</em>', 'Deck &amp; Bar'],
            'keeps a 64 character label' => [str_repeat('a', 64), str_repeat('a', 64)],
        ];
    }

    #[DataProvider('labelProvider')]
    public function test_normalize_label_cleans_input(string $input, string $expected): void
    {
        $this->assertSame($expected, (new TableService())->normalizeLabel($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyLabelProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
            'tags only' => ['<b> </b>'],
            'comment only' => ['<!-- gone -->'],
        ];
    }

    #[DataProvider('emptyLabelProvider')]
    public function test_normalize_label_rejects_empty(string $input): void
    {
        $this->expectException(TableException::class);

        (new TableService())->normalizeLabel($input);
    }

    public function test_normalize_label_rejects_over_64_characters(): void
    {
        $this->expectException(TableException::class);

        (new TableService())->normalizeLabel(str_repeat('a', 65));
    }

    public function test_menu_url_appends_encoded_label(): void
    {
        $this->assertSame(
            'https://example.test/menu/?table=Table%201',
            (new TableService())->menuUrl('https://example.test/menu/', 'Table 1')
        );
    }

    public function test_menu_url_appends_to_an_existing_query(): void
    {
        $this->assertSame(
            'https://example.test/menu/?x=1&table=Patio',
            (new TableService())->menuUrl('https://example.test/menu/?x=1', 'Patio')
        );
    }

    public function test_menu_url_normalizes_the_label(): void
    {
        $this->assertSame(
            'https://example.test/menu/?table=Bar%202',
            (new TableService())->menuUrl('https://example.test/menu/', '  Bar   2 ')
        );
    }

    public function test_menu_url_keeps_an_existing_fragment_at_the_end(): void
    {
        $this->assertSame(
            'https://example.test/menu/?table=Patio#drinks',
            (new TableService())->menuUrl('https://example.test/menu/#drinks', 'Patio')
        );
    }

    public function test_menu_url_combines_an_existing_query_with_a_fragment(): void
    {
        $this->assertSame(
            'https://example.test/menu/?x=1&table=Patio#drinks',
            (new TableService())->menuUrl('https://example.test/menu/?x=1#drinks', 'Patio')
        );
    }

    public function test_menu_url_does_not_duplicate_the_separator(): void
    {
        $this->assertSame(
            'https://example.test/menu/?table=Patio',
            (new TableService())->menuUrl('https://example.test/menu/?', 'Patio')
        );
    }

    public function test_menu_url_carries_no_session_token(): void
    {
        $url = (new TableService())->menuUrl('https://example.test/menu/', 'Patio');

        $this->assertStringNotContainsString('token', $url);
        $this->assertStringNotContainsString('session', $url);
    }
}
