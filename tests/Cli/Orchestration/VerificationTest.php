<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Verification;
use PHPUnit\Framework\TestCase;

/**
 * The difference between a receipt and a report: the agent names the command, the TOOL runs it, and the
 * number filed is the one a process returned. An agent that wanted a different number would have to
 * change what the command prints.
 */
final class VerificationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-verify-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_it_files_the_code_the_process_returned(): void
    {
        $green = new Verification($this->root)->of('item', "php -r 'exit(0);'", '');
        $red = new Verification($this->root)->of('item', "php -r 'exit(3);'", '');

        $this->assertTrue($green->isGreen());
        $this->assertSame(3, $red->exitCode);
        $this->assertFalse($red->isGreen());
    }

    public function test_it_records_the_command_it_actually_ran(): void
    {
        $this->assertSame("php -r 'exit(0);'", new Verification($this->root)->of('item', "php -r 'exit(0);'", '')->argv);
    }

    /**
     * A base nobody asked about is stated as absent rather than blank — "not asked" and "could not be
     * resolved" are different facts, and a blank would let a reader assume the first.
     */
    public function test_an_unasked_base_is_said_to_be_absent(): void
    {
        $this->assertSame('-', new Verification($this->root)->of('item', 'true', '')->mergeBase);
    }

    /**
     * A base that cannot be resolved says the same thing rather than inventing a sha.
     */
    public function test_an_unresolvable_base_is_absent_too(): void
    {
        $this->assertSame('-', new Verification($this->root)->of('item', 'true', 'no-such-branch')->mergeBase);
    }
}
