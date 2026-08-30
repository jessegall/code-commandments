<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\PhpTypes\Option;
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

    private function profileWith(string $document, string $body): void
    {
        $path = $this->root . '/.commandments/orchestrator/profiles/dogfood/' . $document . '.md';

        mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);

        Instance::inSession(new Workspace($this->root, 'sess-1'))->start('dogfood', '10:00');
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

    /**
     * The standing habits a profile's author decided are done EVERY time the work stops. A nudge, never a
     * gate — a habit worth repeating is not a rule worth refusing over.
     */
    public function test_the_profiles_routine_is_repeated_at_every_stop(): void
    {
        $this->profileWith('routine', "# routine\n\n**Told the workflows agent?** Every commit that has been pushed.");

        $said = implode("\n", $this->said());

        $this->assertStringContainsString('Told the workflows agent?', $said);
        $this->assertStringContainsString('dogfood', $said, 'it names the profile the routine came from');
    }

    /**
     * The routine rides on a stop that would otherwise be silent, so it does not need a build running.
     */
    public function test_the_routine_is_said_even_with_no_board(): void
    {
        $this->profileWith('routine', 'Check the record says what you would say out loud.');

        $this->assertStringContainsString('say out loud', implode("\n", $this->said()));
    }

    public function test_a_profile_with_no_routine_says_nothing(): void
    {
        $this->profileWith('behaviour', 'how this team works');

        $this->assertSame([], $this->said());
    }

    public function test_nothing_is_said_when_no_profile_is_in_force(): void
    {
        $this->assertSame([], $this->said());
    }

    /**
     * Once per STRETCH OF WORK, not once per stop. Four identical firings while waiting on one suite is
     * a nudge with nothing new in it — and one of those every time teaches a reader to skim the block
     * that will eventually hold something.
     */
    public function test_the_routine_stays_quiet_when_nothing_has_been_said_since(): void
    {
        $this->profileWith('routine', 'Check the record says what you would say out loud.');

        $this->assertNotSame([], $this->said(), 'it speaks the first time');
        $this->assertSame([], $this->said(), 'and not again with nothing in between');
    }

    public function test_the_routine_speaks_again_once_more_work_has_been_done(): void
    {
        $this->profileWith('routine', 'Check the record says what you would say out loud.');
        $this->said();

        // WORK, not words. A message is itself a journal entry, so pacing on entries would earn the
        // checklist back every time the agent spoke — which is every stop.
        $journal = Journal::inSession(new Workspace($this->root, 'sess-1'));

        // Asked of the hook, never restated here — a threshold spelled in two places is one that drifts
        // the moment either moves.
        foreach (range(1, BoardReminder::A_STRETCH) as $ignored) {
            $journal->countCall();
        }

        $this->assertNotSame([], $this->said(), 'a stretch of work earns the checklist again');
    }

}
