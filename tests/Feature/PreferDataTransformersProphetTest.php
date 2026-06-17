<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\PreferDataTransformersProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferDataTransformersProphetTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/PreferDataTransformers');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [PreferDataTransformersProphet::class => []]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_a_method_hand_mapping_a_data_object(): void
    {
        $results = $this->manager->judgeScroll('test');

        $warning = $this->firstWarningFor($results, $this->fixtureDir . '/Serialiser.php');
        $this->assertNotNull($warning, 'serialise(FooData) hand-maps 3 properties — flag it.');
        $this->assertStringContainsString('serialise', $warning->message);
        $this->assertStringContainsString('toArray()', $warning->message);
        $this->assertStringContainsString('FooData', $warning->message);
    }

    public function test_does_not_flag_few_property_reads_or_a_non_data_param(): void
    {
        $results = $this->manager->judgeScroll('test');

        $messages = implode("\n", array_map(
            fn (Warning $w) => $w->message,
            $this->warningsFor($results, $this->fixtureDir . '/Serialiser.php'),
        ));

        // tiny() reads one property; notData() takes a non-Data class.
        $this->assertStringNotContainsString('tiny()', $messages);
        $this->assertStringNotContainsString('notData()', $messages);
    }

    public function test_stays_silent_without_an_index(): void
    {
        $prophet = new PreferDataTransformersProphet;
        $content = file_get_contents($this->fixtureDir . '/Serialiser.php');

        $this->assertTrue($prophet->judge($this->fixtureDir . '/Serialiser.php', $content)->isRighteous());
    }

    /**
     * @param  Collection<string, mixed>  $results
     */
    private function firstWarningFor(Collection $results, string $file): ?Warning
    {
        return $this->warningsFor($results, $file)[0] ?? null;
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

        $judgment = $results->get($file)->get(PreferDataTransformersProphet::class);

        return $judgment === null ? [] : array_values($judgment->warnings);
    }
}
