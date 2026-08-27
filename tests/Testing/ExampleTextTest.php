<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Testing\ExampleText;
use PHPUnit\Framework\TestCase;

/**
 * How a worked example is assembled from marked fixture sources — which resolution answers which sin,
 * and which of them belong to ONE fix.
 *
 * The rule under test is that a fix which MOVES behaviour spans declarations: the caller that got
 * thinner and the type that received the method are both the fix, and a published example showing only
 * the first teaches a reader to call a method nothing declares.
 */
final class ExampleTextTest extends TestCase
{
    public function test_a_lone_resolution_is_the_whole_fix(): void
    {
        $bad = [self::marked('Fee', 'Fee.php', 'public function price() {}')];
        $good = [self::marked('Fee', 'Fee.php', 'public function priceTold() {}')];

        $resolution = ExampleText::resolution($bad, $good, 'class');

        $this->assertCount(1, $resolution);
        $this->assertSame('public function priceTold() {}', $resolution[0]['source']);
    }

    public function test_the_counterpart_leads_so_the_halves_still_line_up(): void
    {
        $bad = [self::marked('Fee', 'Fee.php', 'sinful')];
        $good = [
            self::marked('PricedFreight', 'Fee.php', 'interface PricedFreight {}'),
            self::marked('Fee', 'Fee.php', 'the thinned caller'),
        ];

        $resolution = ExampleText::resolution($bad, $good, 'class');

        $this->assertSame('the thinned caller', $resolution[0]['source']);
        $this->assertSame('interface PricedFreight {}', $resolution[1]['source']);
    }

    public function test_only_resolutions_in_the_counterparts_file_join_the_fix(): void
    {
        $bad = [self::marked('Fee', 'Fee.php', 'sinful')];
        $good = [
            self::marked('Fee', 'Fee.php', 'the thinned caller'),
            self::marked('Express', 'Fee.php', 'the collaborator'),
            self::marked('Roster', 'Roster.php', 'another scenario entirely'),
        ];

        $resolution = ExampleText::resolution($bad, $good, 'class');

        $this->assertCount(2, $resolution);
        $this->assertSame(['the thinned caller', 'the collaborator'], array_column($resolution, 'source'));
    }

    public function test_a_sin_fixed_in_several_files_keeps_its_scenarios_apart(): void
    {
        $bad = [self::marked('Roster', 'Roster.php', 'sinful')];
        $good = [
            self::marked('Evidence', 'Evidence.php', 'one scenario'),
            self::marked('Roster', 'Roster.php', 'another'),
        ];

        $resolution = ExampleText::resolution($bad, $good, 'class');

        $this->assertCount(1, $resolution);
        $this->assertSame('another', $resolution[0]['source']);
    }

    public function test_nothing_marked_as_fixed_resolves_to_nothing(): void
    {
        $this->assertSame([], ExampleText::resolution([self::marked('Fee', 'Fee.php', 'sinful')], [], 'class'));
    }

    public function test_a_pair_still_comes_from_one_scenario(): void
    {
        $bad = [
            self::marked('Roster', 'Roster.php', 'the sinful roster'),
            self::marked('Evidence', 'Evidence.php', 'the sinful evidence'),
        ];
        $good = [self::marked('Evidence', 'Evidence.php', 'the fixed evidence')];

        $example = ExampleText::pair($bad, $good, 'class');

        $this->assertSame('the sinful evidence', $example->bad());
        $this->assertSame('the fixed evidence', $example->good());
    }

    public function test_a_pair_sharing_no_scenario_falls_back_to_the_first_of_each(): void
    {
        $bad = [self::marked('Roster', 'Roster.php', 'the sinful roster')];
        $good = [self::marked('Evidence', 'Evidence.php', 'the fixed evidence')];

        $example = ExampleText::pair($bad, $good, 'class');

        $this->assertSame('the sinful roster', $example->bad());
        $this->assertSame('the fixed evidence', $example->good());
    }

    public function test_each_block_of_a_group_wears_its_own_heading(): void
    {
        $group = ExampleText::group([
            self::marked('Fee', 'Fee.php', 'thinned()', '// in Shop\Fee'),
            self::marked('Express', 'Express.vue', '<Child />', '<!-- in Express.vue -->'),
        ], lift: false);

        $this->assertSame("// in Shop\Fee\nthinned()\n\n<!-- in Express.vue -->\n<Child />", $group);
    }

    public function test_a_block_that_names_itself_is_not_named_twice(): void
    {
        $group = ExampleText::group([
            self::marked('Fee', 'Fee.php', 'thinned()', '// in Shop\Fee'),
            self::marked('PricedFreight', 'Fee.php', 'interface PricedFreight {}'),
        ], lift: false);

        $this->assertSame("// in Shop\Fee\nthinned()\n\ninterface PricedFreight {}", $group);
    }

    public function test_a_grouped_block_lifts_its_docblock_like_a_lone_one(): void
    {
        $group = ExampleText::group([
            self::marked('Fee', 'Fee.php', "/**\n * Prices a consignment.\n */\npublic function price() {}"),
        ], lift: true);

        $this->assertSame("// Prices a consignment.\n\npublic function price() {}", $group);
    }

    /**
     * @return array{class: string, file: string, heading: ?string, source: string}
     */
    private static function marked(string $class, string $file, string $source, ?string $heading = null): array
    {
        return ['class' => $class, 'file' => $file, 'heading' => $heading, 'source' => $source];
    }
}
