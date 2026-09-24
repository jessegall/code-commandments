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
     * @var list<HookResponse>  every response a handler emitted, in order
     */
    public array $emitted = [];

    /**
     * @var list<SinMark>  every sin a handler reported, in order
     */
    public array $activity = [];

    /**
     * @param  array<string, mixed>  $payload  the payload read once by the dispatcher
     */
    public function __construct(private readonly array $payload, GitFiles $git, Parses $parses = new Parses())
    {
        parent::__construct($git, $parses);
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function emit(HookResponse $response, string $event): void
    {
        $this->emitted[] = $response;
    }

    public function activity(SinMark $mark): void
    {
        $this->activity[] = $mark;
    }
}
