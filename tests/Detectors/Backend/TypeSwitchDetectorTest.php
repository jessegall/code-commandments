<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\TypeSwitchDetector;
use PHPUnit\Framework\TestCase;

final class TypeSwitchDetectorTest extends TestCase
{
    public function test_flags_every_spelling_of_a_type_switch_once(): void
    {
        $code = <<<'PHP'
        <?php
        interface Shipment {}
        class Express implements Shipment {}
        class Freight implements Shipment {}
        class S {
            public function ladder(Shipment $shipment): int {
                if ($shipment instanceof Express) {
                    return $shipment->weight() * 12;
                } elseif ($shipment instanceof Freight) {
                    return $shipment->pallets() * 4000;
                }

                return $shipment->weight() * 3;
            }
            public function sequential(Shipment $shipment): string {
                if ($shipment instanceof Express) {
                    return 'PRIORITY';
                }

                if ($shipment instanceof Freight) {
                    return 'PALLET';
                }

                return 'STANDARD';
            }
            public function matched(Shipment $shipment): string {
                return match (true) {
                    $shipment instanceof Express => 'PRIORITY',
                    $shipment instanceof Freight => 'PALLET',
                    default => 'STANDARD',
                };
            }
        }
        PHP;

        $hits = (new TypeSwitchDetector)->find(Codebase::fromString($code));

        $this->assertSame(
            ['S::ladder', 'S::sequential', 'S::matched'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
    }

    public function test_leaves_a_single_test_a_union_and_separate_subjects_alone(): void
    {
        $code = <<<'PHP'
        <?php
        interface Shipment {}
        class Express implements Shipment {}
        class Freight implements Shipment {}
        class Carrier {}
        class Preferred extends Carrier {}
        class S {
            public function guard(Shipment $shipment): int {
                if (! $shipment instanceof Express) {
                    return 0;
                }

                return $shipment->weight();
            }
            public function union(Shipment $shipment): bool {
                if ($shipment instanceof Express || $shipment instanceof Freight) {
                    return true;
                }

                return false;
            }
            public function differentSubjects(Shipment $shipment, Carrier $carrier): int {
                if ($shipment instanceof Express) {
                    return 1;
                }

                if ($carrier instanceof Preferred) {
                    return 2;
                }

                return 0;
            }
            public function plainBoolean(Shipment $shipment): bool {
                return $shipment instanceof Express;
            }
        }
        PHP;

        $hits = (new TypeSwitchDetector)->find(Codebase::fromString($code));

        // One test is narrowing, not switching. A union asks ONE question in one branch.
        // Two subjects are two unrelated questions.
        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
