<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\RepeatedFallbackProphet;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class RepeatedFallbackCrossFileTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/RepeatedFallbackCrossFile');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [
            RepeatedFallbackProphet::class => [],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_repeated_coalesce_chain_in_both_files(): void
    {
        $results = $this->manager->judgeScroll('test');

        $sinA = $this->firstSinFor($results, $this->fixtureDir . '/ConsumerA.php');
        $this->assertNotNull($sinA, 'ConsumerA should be flagged.');
        $this->assertStringContainsString('Pipeline::current()?->child() ?? Pipeline::make()', $sinA->message);
        $this->assertStringContainsString('2×', $sinA->message);
        $this->assertStringContainsString('Pipeline::currentChildOrMake()', $sinA->suggestion);

        $sinB = $this->firstSinFor($results, $this->fixtureDir . '/ConsumerB.php');
        $this->assertNotNull($sinB, 'ConsumerB should be flagged too.');
        $this->assertStringContainsString('ConsumerA.php', $sinB->message);
    }

    public function test_flags_repeated_full_ternary_null_check(): void
    {
        $results = $this->manager->judgeScroll('test');

        $sin = $this->firstSinFor($results, $this->fixtureDir . '/TernaryConsumerA.php');
        $this->assertNotNull($sin, 'TernaryConsumerA should be flagged.');
        $this->assertStringContainsString('Pipeline::currentOrMake()', $sin->suggestion);
    }

    public function test_does_not_flag_a_chain_that_appears_only_once(): void
    {
        $results = $this->manager->judgeScroll('test');

        $this->assertNull(
            $this->firstSinFor($results, $this->fixtureDir . '/UniqueConsumer.php'),
            'A chain that appears only once must not be flagged.',
        );
    }

    public function test_does_not_flag_null_object_fallbacks(): void
    {
        $results = $this->manager->judgeScroll('test');

        $this->assertNull(
            $this->firstSinFor($results, $this->fixtureDir . '/NullObjectConsumerA.php'),
            'A `?? new NullPipeline` fallback belongs to the null-object prophet.',
        );
    }

    public function test_silent_in_single_file_mode_without_index(): void
    {
        // Directly judging one file (no codebase index injected) can't establish
        // repetition, so the prophet must stay silent.
        $prophet = new RepeatedFallbackProphet;
        $content = file_get_contents($this->fixtureDir . '/ConsumerA.php');

        $this->assertTrue($prophet->judge($this->fixtureDir . '/ConsumerA.php', $content)->isRighteous());
    }

    /**
     * @param  Collection<string, mixed>  $results
     */
    private function firstSinFor(Collection $results, string $file): ?Sin
    {
        if (! $results->has($file)) {
            return null;
        }

        $judgment = $results->get($file)->get(RepeatedFallbackProphet::class);

        if ($judgment === null || ! $judgment->isFallen()) {
            return null;
        }

        return $judgment->sins[0] ?? null;
    }
}
