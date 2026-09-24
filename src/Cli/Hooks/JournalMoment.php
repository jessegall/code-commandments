<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

/**
 * One moment the agent journal hands its plugin — which hook fired, for which session and tool — read
 * once from the journal's own JSON shape.
 */
final readonly class JournalMoment
{
    private function __construct(
        public string $event,
        public string $cwd,
        public ?string $session = null,
        public ?string $tool = null,
        public ?string $command = null,
        public ?string $file = null,
        public ?string $env = null,
    ) {}

    /**
     * @param  array<string, mixed>  $given  the journal's payload: `event`, `env`, `tool`, `data`, `agent`, `project`
     */
    public static function fromPayload(array $given): self
    {
        $tool = is_array($given['tool'] ?? null) ? $given['tool'] : [];
        $data = is_array($given['data'] ?? null) ? $given['data'] : [];
        $agent = is_array($given['agent'] ?? null) ? $given['agent'] : [];
        $event = (string) ($given['event'] ?? '');

        return new self(
            event: str_starts_with($event, 'hook.') ? substr($event, 5) : $event,
            cwd: (string) ($agent['cwd'] ?? $given['project'] ?? getcwd()),
            session: $agent['session'] ?? null,
            tool: $tool['name'] ?? $data['tool'] ?? null,
            command: $tool['command'] ?? $data['command'] ?? null,
            file: $tool['file'] ?? $data['file'] ?? null,
            env: $given['env'] ?? null,
        );
    }

    /**
     * $command as a line of the journal's queue, run in the environment this moment came from — the queue
     * otherwise drains into the project's default, which need not be the agent's.
     */
    public function addressed(string $command): string
    {
        return $this->env === null ? $command : '--env ' . escapeshellarg($this->env) . ' ' . $command;
    }

    public function isPostToolUse(): bool
    {
        return $this->event === 'PostToolUse';
    }

    /**
     * This moment in the shape the hook handlers read — the payload Claude Code's own hooks receive.
     *
     * @return array<string, mixed>
     */
    public function hookPayload(): array
    {
        return array_filter([
            'hook_event_name' => $this->event,
            'session_id' => $this->session,
            'cwd' => $this->cwd,
            'tool_name' => $this->tool,
            'tool_input' => array_filter(['command' => $this->command, 'file_path' => $this->file], static fn (?string $value): bool => $value !== null),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
