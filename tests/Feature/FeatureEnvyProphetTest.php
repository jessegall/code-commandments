<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\FeatureEnvyProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class FeatureEnvyProphetTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/FeatureEnvy');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [FeatureEnvyProphet::class => []]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_a_query_over_a_foreign_owned_object(): void
    {
        $results = $this->manager->judgeScroll('test');
        $warnings = $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php');

        $methods = array_map(static fn (Warning $w): string => $w->message, $warnings);

        $this->assertNotEmpty($warnings, 'EnvyResolver queries NodeDescriptor internals — flag it.');
        $this->assertTrue(
            (bool) array_filter($methods, static fn (string $m): bool => str_contains($m, 'findOutput')),
            'findOutput should be flagged.',
        );
        $this->assertStringContainsString('NodeDescriptor', $warnings[0]->message);
        $this->assertStringContainsString('tell-don\'t-ask', $warnings[0]->message);
    }

    public function test_flags_in_array_over_a_foreign_collection_method(): void
    {
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        $this->assertTrue(
            (bool) array_filter($messages, static fn (string $m): bool => str_contains($m, 'isControlHandle')),
            'isControlHandle (in_array over $descriptor->...HandleNames()) should be flagged.',
        );
    }

    public function test_does_not_flag_querying_your_own_collection(): void
    {
        // NodeDescriptor::findOutputHere queries $this->outputs — own data.
        $results = $this->manager->judgeScroll('test');
        $warnings = $this->warningsFor($results, $this->fixtureDir . '/NodeDescriptor.php');

        $this->assertEmpty($warnings, 'Querying $this own collection is not feature envy.');
    }

    public function test_does_not_flag_a_method_touching_no_foreign_object(): void
    {
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('ownWork', $message);
        }
    }

    public function test_does_not_flag_a_data_mapper_array_map(): void
    {
        // array_map(fn => *Data::from(...), $foreign->coll) is a presentation
        // mapper — moving it onto the domain owner would invert the dependency.
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('toDtos', $message);
        }
    }

    public function test_does_not_flag_a_query_over_a_serialization_boundary(): void
    {
        // array_map(…, $foreign->toArray()) reads the exported form, not internals.
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('summarise', $message);
        }
    }

    public function test_stays_silent_without_an_index(): void
    {
        $prophet = new FeatureEnvyProphet;
        $content = file_get_contents($this->fixtureDir . '/EnvyResolver.php');

        $this->assertTrue($prophet->judge($this->fixtureDir . '/EnvyResolver.php', $content)->isRighteous());
    }

    /**
     * @param  Collection<string, mixed>  $results
     * @return list<Warning>
     */
    private function warningsFor(Collection $results, string $file): array
    {
        if (! $results->has($file)) {
            return [];
        }

        $judgment = $results->get($file)->get(FeatureEnvyProphet::class);

        return $judgment === null ? [] : array_values($judgment->warnings);
    }
}
