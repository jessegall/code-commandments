<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A {@see HookIO} that feeds a fixed payload and captures what the hook emits, instead of reading
 * STDIN and writing STDOUT — so a hook's decision is asserted directly, no process plumbing.
 */
final class CapturingHookIO extends HookIO
{
    /** @var list<HookResponse> Every response the hook emitted, in order. */
    public array $emitted = [];

    /**
     * @param  array<string, mixed>  $stub
     */
    public function __construct(GitFiles $git, private readonly array $stub = [])
    {
        parent::__construct($git);
    }

    public function payload(): array
    {
        return $this->stub;
    }

    public function emit(HookResponse $response, string $event): void
    {
        $this->emitted[] = $response;
    }
}
