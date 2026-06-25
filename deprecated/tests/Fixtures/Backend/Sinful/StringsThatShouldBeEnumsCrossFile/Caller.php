<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * Four call sites against `Broadcaster::dispatch`, two distinct literals.
 *
 * Notably, this file does NOT import `MirroringAction` — Pattern 3's
 * cross-file enum match still needs to find it because every literal
 * lines up with a case of that enum.
 */
class Caller
{
    public function __construct(
        private readonly Broadcaster $broadcaster,
    ) {}

    public function run(): void
    {
        $this->broadcaster->dispatch('publish');
        $this->broadcaster->dispatch('unpublish');
        $this->broadcaster->dispatch('publish');
        $this->broadcaster->dispatch('publish');
    }
}
