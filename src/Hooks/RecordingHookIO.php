<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A {@see HookIO} that feeds every handler the same already-read payload and records what each one
 * emits, rather than reading STDIN again or writing STDOUT — so {@see HookDispatch} can gather the
 * handlers' responses and merge them into a single reply.
 */
final class RecordingHookIO extends HookIO
{
    /**
     * @var list<array<string, mixed>>  every payload a handler emitted, in order
     */
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
