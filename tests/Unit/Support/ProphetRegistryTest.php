<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Prophets\Frontend\StyleOverridesProphet;
use JesseGall\CodeCommandments\Prophets\Frontend\NoFetchAxiosProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Tests\TestCase;

class ProphetRegistryTest extends TestCase
{
    private ProphetRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ProphetRegistry();
    }

    public function test_register_many_with_indexed_array(): void
    {
        $this->registry->registerMany('frontend', [
            NoFetchAxiosProphet::class,
            StyleOverridesProphet::class,
        ]);

        $this->assertEquals(2, $this->registry->count('frontend'));
    }

    public function test_register_many_with_associative_array(): void
    {
        $this->registry->registerMany('frontend', [
            StyleOverridesProphet::class => [
                'allowed_patterns' => ['/^flex$/'],
            ],
        ]);

        $this->assertEquals(1, $this->registry->count('frontend'));
        $this->assertEquals(
            ['allowed_patterns' => ['/^flex$/']],
            $this->registry->getProphetConfig('frontend', StyleOverridesProphet::class)
        );
    }

    public function test_register_many_with_mixed_array(): void
    {
        $this->registry->registerMany('frontend', [
            NoFetchAxiosProphet::class,
            StyleOverridesProphet::class => [
                'allowed_patterns' => ['/^cursor-/'],
            ],
        ]);

        $this->assertEquals(2, $this->registry->count('frontend'));
        $this->assertEquals([], $this->registry->getProphetConfig('frontend', NoFetchAxiosProphet::class));
        $this->assertEquals(
            ['allowed_patterns' => ['/^cursor-/']],
            $this->registry->getProphetConfig('frontend', StyleOverridesProphet::class)
        );
    }

    public function test_get_prophets_merges_thresholds_with_prophet_config(): void
    {
        $this->registry->registerMany('frontend', [
            StyleOverridesProphet::class => [
                'allowed_patterns' => ['/^flex$/'],
            ],
        ]);

        $this->registry->setScrollConfig('frontend', [
            'thresholds' => [
                'max_lines' => 200,
            ],
        ]);

        $prophets = $this->registry->getProphets('frontend');
        $this->assertCount(1, $prophets);

        // The prophet should have both thresholds and prophet-specific config
        $prophet = $prophets->first();
        $this->assertInstanceOf(StyleOverridesProphet::class, $prophet);
    }

    public function test_set_and_get_prophet_config(): void
    {
        $this->registry->register('frontend', StyleOverridesProphet::class);
        $this->registry->setProphetConfig('frontend', StyleOverridesProphet::class, [
            'allowed_patterns' => ['/^w-/', '/^h-/'],
        ]);

        $config = $this->registry->getProphetConfig('frontend', StyleOverridesProphet::class);
        $this->assertEquals([
            'allowed_patterns' => ['/^w-/', '/^h-/'],
        ], $config);
    }

    public function test_get_prophet_config_returns_empty_array_when_not_set(): void
    {
        $this->registry->register('frontend', NoFetchAxiosProphet::class);

        $config = $this->registry->getProphetConfig('frontend', NoFetchAxiosProphet::class);
        $this->assertEquals([], $config);
    }

    public function test_get_prophets_filters_out_unsupported_prophets(): void
    {
        $this->registry->register('test', \JesseGall\CodeCommandments\Tests\Fixtures\Prophets\SupportedTestProphet::class);
        $this->registry->register('test', \JesseGall\CodeCommandments\Tests\Fixtures\Prophets\UnsupportedTestProphet::class);

        $prophets = $this->registry->getProphets('test');

        $this->assertCount(1, $prophets);
        $this->assertTrue($prophets->first()->supported());
    }
}
