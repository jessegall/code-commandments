<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Doctrines\Doctrine;
use JesseGall\CodeCommandments\Doctrines\DoctrineCascade;
use JesseGall\CodeCommandments\Doctrines\DoctrineRegistry;
use JesseGall\CodeCommandments\Doctrines\Ranked;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeTypedAccessorProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeCoalesceProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ShortClosureProphet;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    // ── Doctrine value object ────────────────────────────────────────────────

    public function test_band_of_returns_the_coarse_to_fine_index(): void
    {
        $d = new Doctrine('x', [['A', 'B'], ['C'], ['D']]);

        $this->assertSame(0, $d->bandOf('A'));
        $this->assertSame(0, $d->bandOf('B'));
        $this->assertSame(1, $d->bandOf('C'));
        $this->assertSame(2, $d->bandOf('D'));
        $this->assertNull($d->bandOf('Z'));
        $this->assertSame(['A', 'B', 'C', 'D'], $d->members());
    }

    // ── DoctrineRegistry (seeded with Totality) ──────────────────────────────

    public function test_registry_locates_totality_members_with_coarse_to_fine_bands(): void
    {
        // Boundary is the coarsest band; the T_*::coalesce hygiene nitpick the finest.
        $boundary = DoctrineRegistry::locate(PreferNativeTypedAccessorProphet::class);
        $source = DoctrineRegistry::locate(OptionDisciplineProphet::class);
        $nitpick = DoctrineRegistry::locate(PreferTypeCoalesceProphet::class);

        $this->assertSame('totality', $boundary['doctrine']);
        $this->assertSame(0, $boundary['band']);
        $this->assertSame('totality', $source['doctrine']);
        $this->assertGreaterThan($boundary['band'], $source['band']);
        $this->assertGreaterThan($source['band'], $nitpick['band']);
    }

    public function test_registry_returns_null_for_a_singleton_prophet(): void
    {
        $this->assertNull(DoctrineRegistry::locate(ShortClosureProphet::class));
        $this->assertNull(DoctrineRegistry::doctrineOf(ShortClosureProphet::class));
    }

    // ── DoctrineCascade ──────────────────────────────────────────────────────

    public function test_a_coarser_band_suppresses_finer_ones_in_the_same_region(): void
    {
        $survivors = $this->survivors([
            $this->ranked('boundary', 'totality', 0, 5, 12),
            $this->ranked('source', 'totality', 2, 6, 6),
            $this->ranked('nitpick', 'totality', 5, 7, 7),
        ]);

        $this->assertSame(['boundary'], $survivors);
    }

    public function test_same_band_peers_do_not_suppress_each_other(): void
    {
        // Two sibling root causes in the same band — both survive (neither is coarser).
        $survivors = $this->survivors([
            $this->ranked('causeA', 'totality', 1, 4, 10),
            $this->ranked('causeB', 'totality', 1, 5, 9),
        ]);

        $this->assertSame(['causeA', 'causeB'], $survivors);
    }

    public function test_findings_in_different_methods_do_not_suppress(): void
    {
        $survivors = $this->survivors([
            $this->ranked('coarse', 'totality', 0, 1, 5),
            $this->ranked('fine', 'totality', 5, 20, 25),
        ]);

        $this->assertSame(['coarse', 'fine'], $survivors);
    }

    public function test_different_doctrines_in_the_same_region_both_show(): void
    {
        $survivors = $this->survivors([
            $this->ranked('totality', 'totality', 0, 5, 10),
            $this->ranked('enum', 'enum', 0, 6, 8),
        ]);

        $this->assertSame(['totality', 'enum'], $survivors);
    }

    public function test_singletons_are_never_suppressed(): void
    {
        $survivors = $this->survivors([
            $this->ranked('coarse', 'totality', 0, 5, 12),
            $this->ranked('singleton', null, null, 6, 6),
        ]);

        $this->assertSame(['coarse', 'singleton'], $survivors);
    }

    public function test_suppression_is_scoped_to_one_file(): void
    {
        $survivors = $this->survivors([
            $this->ranked('coarse', 'totality', 0, 5, 12, 'A.php'),
            $this->ranked('fine', 'totality', 5, 6, 6, 'B.php'),
        ]);

        $this->assertSame(['coarse', 'fine'], $survivors);
    }

    /**
     * @param  list<Ranked>  $findings
     * @return list<string>
     */
    private function survivors(array $findings): array
    {
        return array_map(static fn (Ranked $r): string => $r->finding, DoctrineCascade::apply($findings));
    }

    private function ranked(string $label, ?string $doctrine, ?int $band, int $start, int $end, string $path = 'a.php'): Ranked
    {
        return new Ranked($label, $doctrine, $band, $path, $start, $end);
    }
}
