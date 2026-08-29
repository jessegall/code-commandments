<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\OrchestrateCommand;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * `commandments orchestrate --write` — the proposal spliced in rather than printed to paste. A block a
 * tool can write and asks a person to copy is a cost moved onto whoever adopts it, and the paste is
 * where the typo lands. Its twin is `layers --write`, which is also where the two rules under test come
 * from: the edit goes through the AST so a formatted config survives it, and a block already declared is
 * REFUSED rather than overwritten — non-zero, because anything chaining behind the write reads a zero as
 * a grant.
 */
final class OrchestrateWriteTest extends TestCase
{
    private string $root;

    private string $config;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-orchestrate-write-' . uniqid('', true);
        $this->config = $this->root . '/.commandments/config.php';
        mkdir($this->root . '/.commandments', 0777, true);

        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        file_put_contents($this->config, self::CONFIG);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * A config shaped like a real one: the `$disabledSins` menu is a closure FULL of `disable()` calls
     * standing above the config's own closure, which is the trap a block anchored on the first
     * `disable()` in the file falls into — it lands inside the menu, reading as a disablement.
     */
    private const string CONFIG = <<<'PHP_SOURCE'
        <?php

        declare(strict_types=1);

        use JesseGall\CodeCommandments\Config;

        /**
         * Uncomment a line to disable that single sin.
         */
        $disabledSins = function (Config $config): void {
            $config->disable(
                // \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
            );
        };

        return function (Config $config) use ($disabledSins): void {
            $config->paths('src');

            $config->disable();

            $disabledSins($config);
        };

        PHP_SOURCE;

    /**
     * @return array{0: int, 1: string}  the exit code and everything the command printed
     */
    private function orchestrate(string $branch, string ...$args): array
    {
        $out = fopen('php://memory', 'r+');

        $code = new OrchestrateCommand(new CapturingHookIO(new FakeGit($this->root, branch: $branch)), new Console($out))
            ->run(Input::of('orchestrate', $args));

        rewind($out);

        return [$code, (string) stream_get_contents($out)];
    }

    private function declared(): \JesseGall\CodeCommandments\OrchestrationProfile
    {
        return Config::load($this->root)->orchestrationSettings();
    }

    public function test_write_declares_the_block_the_bare_form_prints(): void
    {
        [$code] = $this->orchestrate('main', '--write');

        $this->assertSame(0, $code);

        $declared = $this->declared();

        $this->assertSame('main', $declared->branch()->unwrapOr(''), 'the branch is the one the checkout is on');
        $this->assertSame('integrator', $declared->writer()->unwrapOr(''));
        $this->assertSame(3, $declared->running);
        $this->assertSame(2, $declared->prefer);
    }

    /**
     * One renderer: what is printed to paste and what is written must be the same text, or the two drift
     * and the pasted block stops being the block the tool tested.
     */
    public function test_the_printed_proposal_is_the_block_that_gets_written(): void
    {
        [$code, $printed] = $this->orchestrate('main');

        $this->assertSame(0, $code);
        $this->assertStringContainsString(OrchestrateCommand::declaration('main'), $printed);
        $this->assertStringNotContainsString('orchestration', (string) file_get_contents($this->config), 'the bare form writes nothing');
    }

    /**
     * The `layers` rule, kept: a declared block was decided by somebody and read in a diff, and nothing
     * here can tell which of its values were deliberate.
     */
    public function test_it_refuses_a_block_already_declared_rather_than_overwriting_it(): void
    {
        $this->orchestrate('main', '--write');
        $before = (string) file_get_contents($this->config);

        [$code] = $this->orchestrate('other', '--write');

        $this->assertSame(Console::REFUSED, $code, 'a caller chaining behind the write must not read a grant');
        $this->assertSame($before, (string) file_get_contents($this->config), 'the declared block is untouched');
        $this->assertSame('main', $this->declared()->branch()->unwrapOr(''));
    }

    /**
     * The branch the work converges on is the one value the block cannot be written without: an
     * undeclared branch means no merge can be judged at all, so a placeholder written into a config
     * would read as a rule that is on.
     */
    public function test_it_refuses_when_there_is_no_branch_to_declare(): void
    {
        [$code] = $this->orchestrate('', '--write');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringNotContainsString('orchestration', (string) file_get_contents($this->config));
    }

    public function test_the_edited_config_is_still_valid_php(): void
    {
        $this->orchestrate('main', '--write');

        exec('php -l ' . escapeshellarg($this->config) . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
    }

    /**
     * The human's own lines survive the edit — it is an AST splice, not text surgery on a file a
     * formatter has touched.
     */
    public function test_the_rest_of_the_config_is_left_exactly_as_it_was(): void
    {
        $this->orchestrate('main', '--write');

        $after = (string) file_get_contents($this->config);

        $this->assertStringContainsString("\$config->paths('src');", $after);
        $this->assertStringContainsString('$config->disable();', $after);
        $this->assertStringContainsString('$disabledSins($config);', $after);
        $this->assertStringContainsString('// \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,', $after);
    }

    /**
     * The failure `layers` already paid for: anchored on the first `disable()` in the file, the block
     * landed inside the `$disabledSins` menu — valid PHP, never run, and reading as a disablement.
     */
    public function test_the_block_lands_in_the_configs_own_closure_not_in_a_menu(): void
    {
        $this->orchestrate('main', '--write');

        $after = (string) file_get_contents($this->config);

        $this->assertGreaterThan(
            strpos($after, 'return function (Config $config)'),
            strpos($after, '$config->orchestration('),
            'the block must stand in the config\'s own closure, not inside a menu above it',
        );
    }
}
