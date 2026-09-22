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
    public const QUIET = 'COMMANDMENTS_QUIET_HOOKS';

    public const DATA = 'JOURNAL_PLUGIN_DATA';

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

        if (is_dir($payload['cwd'])) {
            chdir($payload['cwd']);
        }

        $event = new HookEvent($payload, $this->io->projectRoot());
        $recorder = new RecordingHookIO($payload, $this->io->git());

        $quiet = $this->quiet();

        foreach (HookRegistry::forProject($event->root) as $class) {
            if (is_subclass_of($class, Hook::class) && ! in_array((new \ReflectionClass($class))->getShortName(), $quiet, true)) {
                new $class($recorder)->run([]);
            }
        }

        $answer = $this->answer(HookResponse::merge($recorder->emitted));
        $activity = $this->activity($payload, $event->root, $recorder->activity);

        if ($activity !== []) {
            $answer['activity'] = $activity;
        }

        echo json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        return 0;
    }

    /**
     * "Sin found" for what this edit broke; "Sin repented" when an edit clears a file that had some.
     * The sins each file had last time are kept in the plugin's data folder.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $found
     * @return array<string, string>
     */
    private function activity(array $payload, string $root, array $found): array
    {
        $file = (string) ($payload['tool_input']['file_path'] ?? '');
        $kept = getenv(self::DATA) ? getenv(self::DATA) . '/sins.json' : '';

        if ($file === '' || $kept === '') {
            return $found === [] ? [] : ['title' => 'Sin found', 'brief' => implode("\n", $found)];
        }

        $file = str_replace(rtrim($root, '/') . '/', '', $file);
        $known = is_file($kept) ? (array) json_decode((string) file_get_contents($kept), true) : [];
        $before = (array) ($known[$file] ?? []);
        $known[$file] = $found;
        file_put_contents($kept, json_encode(array_filter($known), JSON_UNESCAPED_SLASHES));

        if ($found !== []) {
            return ['title' => 'Sin found', 'brief' => implode("\n", $found)];
        }

        return $before === [] ? [] : ['title' => 'Sin repented', 'brief' => implode("\n", $before)];
    }

    /**
     * The hooks the journal plugin's settings keep quiet: short class names, comma separated.
     *
     * @return list<string>
     */
    private function quiet(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) getenv(self::QUIET)))));
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
     * @return array<string, mixed>
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
