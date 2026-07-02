<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Bridge\Contracts;
use JesseGall\CodeCommandments\Bridge\TypeContract;
use JesseGall\CodeCommandments\Detectors\Frontend\MirroredServerTypeDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class MirroredServerTypeDetectorTest extends TestCase
{
    private function detector(TypeContract ...$contracts): MirroredServerTypeDetector
    {
        $detector = new MirroredServerTypeDetector();
        $detector->withContracts(new Contracts()->with(...$contracts));

        return $detector;
    }

    public function test_it_flags_a_type_that_mirrors_a_server_contract(): void
    {
        $component = Codebase::fromString(<<<'VUE'
        <script setup lang="ts">
        interface OrderData {
          id: string
          total: number
          placedAt: string
        }
        </script>
        <template><div /></template>
        VUE);

        $findings = $this->detector(new TypeContract('OrderData', ['id', 'total', 'placedAt']))
            ->find($component);

        $this->assertCount(1, $findings);
        $this->assertSame('type OrderData', $findings[0]->scope());
    }

    public function test_it_matches_across_snake_and_camel_spelling(): void
    {
        $component = Codebase::fromString(<<<'VUE'
        <script setup lang="ts">
        type CustomerData = {
          first_name: string
          last_name: string
          email_address: string
        }
        </script>
        <template><div /></template>
        VUE);

        $findings = $this->detector(new TypeContract('CustomerData', ['firstName', 'lastName', 'emailAddress']))
            ->find($component);

        $this->assertCount(1, $findings);
    }

    public function test_a_frontend_only_view_model_is_not_flagged(): void
    {
        // Same fields as a server contract, but NO server class of this name — a
        // legitimate local shape, its own single source of truth.
        $component = Codebase::fromString(<<<'VUE'
        <script setup lang="ts">
        interface TableColumn {
          id: string
          total: number
          placedAt: string
        }
        </script>
        <template><div /></template>
        VUE);

        $findings = $this->detector(new TypeContract('OrderData', ['id', 'total', 'placedAt']))
            ->find($component);

        $this->assertSame([], $findings);
    }

    public function test_a_thin_type_below_the_field_floor_is_not_flagged(): void
    {
        $component = Codebase::fromString(<<<'VUE'
        <script setup lang="ts">
        interface FlagData {
          id: string
          on: boolean
        }
        </script>
        <template><div /></template>
        VUE);

        $findings = $this->detector(new TypeContract('FlagData', ['id', 'on']))
            ->find($component);

        $this->assertSame([], $findings);
    }

    public function test_without_any_contracts_it_finds_nothing(): void
    {
        $component = Codebase::fromString(<<<'VUE'
        <script setup lang="ts">
        interface OrderData {
          id: string
          total: number
          placedAt: string
        }
        </script>
        <template><div /></template>
        VUE);

        $this->assertSame([], new MirroredServerTypeDetector()->find($component));
    }
}
