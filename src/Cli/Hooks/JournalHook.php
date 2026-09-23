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
use JesseGall\PhpTypes\Option;

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
        echo $this->answerFor($this->io->payload())->toJson() . "\n";

        return 0;
    }

    /**
     * The journal's answer to one moment — `refuse`, `whisper`, `raise` — given its payload. The whole
     * hook, without the pipes: the command answers one moment and exits, {@see JournalServe} answers
     * many from one process.
     *
     * @param  array<string, mixed>  $given  the journal's payload
     */
    public function answerFor(array $given): JournalAnswer
    {
        $moment = JournalMoment::fromPayload($given);

        if (is_dir($moment->cwd)) {
            chdir($moment->cwd);
        }

        $payload = $moment->hookPayload();
        $event = new HookEvent($payload, $this->io->projectRoot());
        $recorder = new RecordingHookIO($payload, $this->io->git(), $this->io->parses());

        $quiet = $this->quiet();

        foreach (HookRegistry::forProject($event->root) as $class) {
            if (is_subclass_of($class, Hook::class) && ! in_array((new \ReflectionClass($class))->getShortName(), $quiet, true)) {
                new $class($recorder)->run([]);
            }
        }

        $answer = $this->answer(HookResponse::merge($recorder->emitted));

        if (! $moment->isPostToolUse()) {
            return $answer;
        }

        return $this->raised($moment, $event->root, $recorder->activity)->mapOr($answer, $answer->raising(...));
    }

    /**
     * The sin-found event for what this edit broke; sin-resolved when an edit clears a file that had some.
     * The sins each file had last time are kept in the plugin's data folder.
     *
     * @param  list<string>  $found
     * @return Option<JournalRaise>
     */
    private function raised(JournalMoment $moment, string $root, array $found): Option
    {
        $data = getenv(self::DATA) ?: null;

        if ($moment->file === null || $data === null) {
            return $found === [] ? Option::none() : Option::some(new JournalRaise('sin-found', implode("\n", $found)));
        }

        $kept = "{$data}/sins.json";
        $file = str_replace(rtrim($root, '/') . '/', '', $moment->file);
        $known = is_file($kept) ? (array) json_decode((string) file_get_contents($kept), true) : [];
        $before = (array) ($known[$file] ?? []);
        $known[$file] = $found;
        file_put_contents($kept, json_encode(array_filter($known), JSON_UNESCAPED_SLASHES));

        if ($found !== [] && $found !== $before) {
            return Option::some(new JournalRaise('sin-found', implode("\n", $found)));
        }

        return $found === [] && $before !== [] ? Option::some(new JournalRaise('sin-resolved', implode("\n", $before))) : Option::none();
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

    private function answer(HookResponse $merged): JournalAnswer
    {
        foreach ($merged->blockReason as $reason) {
            return new JournalAnswer(refuse: $reason, whisper: $reason);
        }

        foreach ($merged->context as $text) {
            return trim($text) === '' ? new JournalAnswer() : new JournalAnswer(whisper: trim($text));
        }

        return new JournalAnswer();
    }
}
