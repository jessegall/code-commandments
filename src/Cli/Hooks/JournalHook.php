<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;

/**
 * `commandments journal-hook` — the entry point the agent journal's plugin calls. The journal owns the
 * hooks in a project that uses it, so the moment arrives in the journal's own shape; this translates it
 * into the payload the handlers read, runs the same registry as {@see HookDispatch}, and answers in the
 * journal's shape: `refuse` to stop the call, `whisper` to tell the agent alone.
 */
final class JournalHook implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['journal-hook'];
    }

    public function help(): Help
    {
        return Help::of("The agent journal's entry point — reads one journal hook payload from stdin, runs every registered handler, and answers in the journal's shape.")
            ->form('journal-hook', 'answer the moment on stdin (wired by the journal plugin; you rarely run this by hand)')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $given = $this->io->payload();
        $payload = $this->translated($given);
        $event = new HookEvent($payload, $this->io->projectRoot());
        $recorder = new RecordingHookIO($payload, $this->io->git());

        foreach (HookRegistry::forProject($event->root) as $class) {
            if (is_subclass_of($class, Hook::class)) {
                new $class($recorder)->run([]);
            }
        }

        echo json_encode($this->answer(HookResponse::merge($recorder->emitted)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        return 0;
    }

    /**
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    private function translated(array $given): array
    {
        $tool = is_array($given['tool'] ?? null) ? $given['tool'] : [];
        $data = is_array($given['data'] ?? null) ? $given['data'] : [];
        $agent = is_array($given['agent'] ?? null) ? $given['agent'] : [];
        $name = (string) ($tool['name'] ?? $data['tool'] ?? '');
        $file = (string) ($tool['file'] ?? $data['file'] ?? '');
        $command = (string) ($tool['command'] ?? $data['command'] ?? '');

        return [
            'hook_event_name' => $this->moment($given),
            'session_id' => (string) ($agent['session'] ?? ''),
            'cwd' => (string) ($agent['cwd'] ?? $given['project'] ?? getcwd()),
            'tool_name' => $name,
            'tool_input' => array_filter([
                'command' => $command,
                'file_path' => $file,
            ], static fn (string $value): bool => $value !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $given
     */
    private function moment(array $given): string
    {
        $event = (string) ($given['event'] ?? '');

        return str_starts_with($event, 'hook.') ? substr($event, 5) : $event;
    }

    /**
     * @return array<string, string>
     */
    private function answer(HookResponse $merged): array
    {
        foreach ($merged->blockReason as $reason) {
            return ['refuse' => $reason, 'whisper' => $reason];
        }

        foreach ($merged->context as $text) {
            return trim($text) === '' ? [] : ['whisper' => trim($text)];
        }

        return [];
    }
}
