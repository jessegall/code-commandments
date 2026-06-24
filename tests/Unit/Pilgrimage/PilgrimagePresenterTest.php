<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use PHPUnit\Framework\TestCase;

class PilgrimagePresenterTest extends TestCase
{
    private PilgrimageRunner $runner;

    protected function setUp(): void
    {
        // Any runner — render() only calls totalDoctrines() on it.
        $this->runner = new PilgrimageRunner('/tmp', ['scrolls' => ['backend' => ['extensions' => ['php']]]], 'backend');
    }

    public function test_renders_progress_bar_and_walked_count(): void
    {
        $out = implode("\n", PilgrimagePresenter::render($this->step(), $this->runner));

        $this->assertStringContainsString('PROGRESS', $out);
        $this->assertStringContainsString('3/40 prophets walked', $out);
        $this->assertMatchesRegularExpression('/\d+%/', $out);
    }

    public function test_renders_the_doctrine_checklist_with_the_current_prophet_marked(): void
    {
        $out = implode("\n", PilgrimagePresenter::render($this->step(), $this->runner));

        $this->assertStringContainsString('THIS DOCTRINE — totality', $out);
        $this->assertStringContainsString('[x] AlphaProphet', $out);   // walked
        $this->assertStringContainsString('[»] BetaProphet', $out);    // current
        $this->assertStringContainsString('[ ] GammaProphet', $out);   // ahead
    }

    public function test_asks_the_agent_to_keep_a_live_todo_for_the_doctrine(): void
    {
        $out = implode("\n", PilgrimagePresenter::render($this->step(), $this->runner));

        $this->assertStringContainsString('Keep a live TODO LIST for this doctrine', $out);
    }

    public function test_completion_reports_the_total_walked(): void
    {
        $out = implode("\n", PilgrimagePresenter::render(['complete' => true, 'prophetsTotal' => 40], $this->runner));

        $this->assertStringContainsString('all 40 prophets', $out);
    }

    /**
     * @return array<string, mixed>
     */
    private function step(): array
    {
        return [
            'complete' => false,
            'prophet' => 'BetaProphet',
            'scripture' => 'rule text',
            'doctrine' => 'totality',
            'doctrineIndex' => 0,
            'pillar' => 0,
            'locations' => [['file' => 'a.php', 'line' => 5, 'message' => 'm', 'autoFixable' => false]],
            'stillUnresolved' => false,
            'doctrineRoster' => ['AlphaProphet', 'BetaProphet', 'GammaProphet'],
            'doctrineProphetPosition' => 1,
            'prophetsWalked' => 3,
            'prophetsTotal' => 40,
        ];
    }
}
