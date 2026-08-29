<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Hooks\Handlers\BoardReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A worker that has reported is not idle and is not working — it is a decision nobody has made. Said at
 * the moment the orchestrator believes it has finished, which is the only moment it can still act.
 */
final class BoardReminderTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-boardhook-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function board(): Board
    {
        return Board::inSession(new Workspace($this->root, 'sess-1'));
    }

    /**
     * @return list<string>
     */
    private function said(): array
    {
        $io = new RecordingHookIO(['hook_event_name' => 'Stop', 'session_id' => 'sess-1'], new FakeGit($this->root));

        new BoardReminder($io)->run([]);

        return array_map(fn ($response) => $response->context->unwrapOr(''), $io->emitted);
    }

    public function test_a_reported_item_is_named_at_a_stop(): void
    {
        $board = $this->board();
        $board->claim('cart-checkout', 'lane-2', '11:42');
        $board->move('cart-checkout', Stage::Reported);

        $said = implode("\n", $this->said());

        $this->assertStringContainsString('cart-checkout', $said);
        $this->assertStringContainsString('waiting on YOU', $said);
        $this->assertStringContainsString('accept', $said, 'it names the act, not only the state');
    }

    /**
     * A blocked worker waits on an answer, which is the one an orchestrator leaves longest because it
     * looks like work in progress.
     */
    public function test_a_blocked_item_is_named_too(): void
    {
        $board = $this->board();
        $board->claim('order-history', 'lane-3', '13:40');
        $board->move('order-history', Stage::Blocked);

        $this->assertStringContainsString('answer', implode("\n", $this->said()));
    }

    /**
     * Work being done is not waiting on anybody, and saying so every turn is what teaches skimming.
     */
    public function test_working_items_are_not_reported(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertSame([], $this->said());
    }

    public function test_a_board_nobody_has_claimed_on_says_nothing(): void
    {
        $this->assertSame([], $this->said());
    }
}
