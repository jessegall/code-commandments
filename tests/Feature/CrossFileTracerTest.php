<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\NoArrayStringIndexingProphet;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class CrossFileTracerTest extends TestCase
{
    private ScrollManager $manager;
    private ProphetRegistry $registry;
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry();
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner());

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/CrossFileCallChain');
        $this->assertNotFalse($this->fixtureDir);
    }

    public function test_prophet_traces_origin_through_ab_to_c(): void
    {
        $this->registerProphet(['max_trace_depth' => 10]);

        $results = $this->manager->judgeScroll('test');

        $cFile = $this->fixtureDir . '/C.php';
        $this->assertTrue($results->has($cFile), "Expected C.php to have sins. Got: " . json_encode($results->keys()->all()));

        $cResults = $results->get($cFile);
        $prophetJudgment = $cResults->get(NoArrayStringIndexingProphet::class);
        $this->assertNotNull($prophetJudgment);
        $this->assertTrue($prophetJudgment->isFallen(), 'Expected C.php to be fallen');

        $traced = $this->findTracedSin($prophetJudgment->sins);
        $this->assertNotNull($traced, 'Expected at least one sin to carry a DTO boundary hint');
        $this->assertStringContainsString('DTO boundary', $traced->suggestion);
        $this->assertStringContainsString('A::ingest()', $traced->suggestion);
        $this->assertStringContainsString('json_decode', $traced->suggestion);
        $this->assertStringContainsString('2 hops upstream', $traced->suggestion);
    }

    public function test_prophet_falls_back_to_local_hint_when_trace_depth_exhausted(): void
    {
        $this->registerProphet(['max_trace_depth' => 1]);

        $results = $this->manager->judgeScroll('test');
        $cFile = $this->fixtureDir . '/C.php';
        $prophetJudgment = $results->get($cFile)->get(NoArrayStringIndexingProphet::class);

        $suggestions = array_map(fn ($s) => $s->suggestion, $prophetJudgment->sins);
        $this->assertNotEmpty($suggestions);

        foreach ($suggestions as $s) {
            $this->assertStringNotContainsString('DTO boundary', $s, 'Depth-1 trace should not reach A::ingest');
        }
    }

    public function test_prophet_still_flags_locally_when_cross_file_trace_disabled(): void
    {
        $this->registerProphet(['cross_file_trace' => false]);

        $results = $this->manager->judgeScroll('test');
        $cFile = $this->fixtureDir . '/C.php';
        $prophetJudgment = $results->get($cFile)->get(NoArrayStringIndexingProphet::class);

        $this->assertTrue($prophetJudgment->isFallen());

        foreach ($prophetJudgment->sins as $sin) {
            $this->assertStringNotContainsString('DTO boundary', $sin->suggestion);
            $this->assertStringContainsString('parameter', $sin->suggestion);
        }
    }

    public function test_single_file_judgement_skips_index_and_uses_local_hint(): void
    {
        $this->registerProphet(['max_trace_depth' => 10]);

        $results = $this->manager->judgeFile('test', $this->fixtureDir . '/C.php');
        $judgment = $results->get(NoArrayStringIndexingProphet::class);

        $this->assertNotNull($judgment);
        $this->assertTrue($judgment->isFallen());

        foreach ($judgment->sins as $sin) {
            $this->assertStringNotContainsString('DTO boundary', $sin->suggestion);
        }
    }

    private function registerProphet(array $config = []): void
    {
        $this->registry->registerMany('test', [
            NoArrayStringIndexingProphet::class => $config,
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    private function findTracedSin(array $sins): ?object
    {
        foreach ($sins as $sin) {
            if (str_contains($sin->suggestion ?? '', 'DTO boundary')) {
                return $sin;
            }
        }

        return null;
    }
}
