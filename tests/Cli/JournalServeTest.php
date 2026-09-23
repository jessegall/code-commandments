<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use PHPUnit\Framework\TestCase;

/**
 * The hook service answers each moment the journal sends over its socket as `journal-hook` would print
 * it, keeps answering after a moment it cannot read, and hangs up after every answer.
 */
final class JournalServeTest extends TestCase
{
    private string $root;

    private string $socket;

    /**
     * @var resource
     */
    private $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-serve-' . uniqid();
        mkdir($this->root . '/.commandments', 0777, true);
        $this->socket = sys_get_temp_dir() . '/cc-serve-' . uniqid() . '.sock';
        $binary = dirname(__DIR__, 2) . '/bin/commandments';
        $environment = [...getenv(), 'JOURNAL_PLUGIN_SOCKET' => $this->socket, 'CLAUDE_PROJECT_DIR' => $this->root];
        $this->service = proc_open([PHP_BINARY, $binary, 'journal-serve'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $this->root, $environment);

        for ($waited = 0; ! file_exists($this->socket) && $waited < 100; $waited++) {
            usleep(50_000);
        }
    }

    protected function tearDown(): void
    {
        proc_terminate($this->service);
        proc_close($this->service);
        @unlink($this->socket);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_moment_no_handler_cares_about_is_answered_with_an_empty_object(): void
    {
        $this->assertSame('{}', $this->ask(['event' => 'hook.PreToolUse', 'agent' => ['session' => 's', 'cwd' => $this->root], 'tool' => ['name' => 'Read', 'file' => 'a.php']]));
    }

    public function test_a_moment_a_handler_answers_comes_back_as_the_command_would_print_it(): void
    {
        $answer = json_decode($this->ask(['event' => 'hook.PreToolUse', 'agent' => ['session' => 's', 'cwd' => $this->root], 'tool' => ['name' => 'Agent', 'command' => '']]), true);

        $this->assertStringContainsString('names no model', $answer['whisper']);
    }

    public function test_a_line_it_cannot_read_is_answered_and_the_next_one_still_is(): void
    {
        $this->assertSame('{}', $this->ask('not json at all'));
        $this->assertSame('{}', $this->ask(['event' => 'hook.PreToolUse', 'agent' => ['session' => 's', 'cwd' => $this->root], 'tool' => ['name' => 'Read']]));
    }

    /**
     * @param  array<string, mixed>|string  $moment
     */
    private function ask(array|string $moment): string
    {
        $client = stream_socket_client("unix://{$this->socket}", $code, $message, 10);
        $this->assertNotFalse($client, "the service is listening on {$this->socket}: {$message}");
        fwrite($client, (is_string($moment) ? $moment : json_encode($moment)) . "\n");
        stream_socket_shutdown($client, STREAM_SHUT_WR);
        $answer = trim((string) stream_get_contents($client));
        fclose($client);

        return $answer;
    }
}
