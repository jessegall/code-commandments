<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A {@see HookIO} that feeds every handler the SAME already-read payload and RECORDS what each one would
 * emit, instead of reading STDIN again or writing STDOUT. {@see HookDispatch} runs the whole registry
 * through one of these so it can gather each handler's response and merge them into a single reply — the
 * production sibling of the test-only `CapturingHookIO`.
 */
final class RecordingHookIO extends HookIO
{
    /** @var list<array<string, mixed>>  every payload a handler emitted, in order */
    public array $emitted = [];

    /**
     * @param  array<string, mixed>  $payload  the payload read once by the dispatcher
     */
    public function __construct(private readonly array $payload, GitFiles $git)
    {
        parent::__construct($git);
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function emit(array $payload): void
    {
        $this->emitted[] = $payload;
    }
}
