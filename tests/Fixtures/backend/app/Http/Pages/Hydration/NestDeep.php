<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNestedFrom;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N1 scenario 3 — nested-in-nested: a wrapper inside a wrapper, reached through a match. Both the panel and
 * its header auto-hydrate their arrays. Plus the righteous twins (object source, ready value).
 */
final class ToolbarShell extends Data
{
    public function __construct(public readonly ToolbarPanel $panel) {}
}

final class ToolbarShellFactory
{
    #[Sinful(RedundantNestedFrom::class)]
    public function forMode(string $mode): ToolbarShell
    {
        $heading = match ($mode) {
            'run' => 'Running',
            'edit' => 'Editing',
            default => 'Idle',
        };

        return ToolbarShell::from(['panel' => ToolbarPanel::from(['header' => HeaderCopy::from(['heading' => $heading])])]);
    }
}

/**
 * RIGHTEOUS: an OBJECT source (`from($model)`) is a real conversion, and a value passed through ready is not
 * built here — the detector must NOT flag either.
 */
final class ReadyBadgeStrip extends Data
{
    public function __construct(public readonly BadgeCopy $badge) {}
}

final class ReadyBadgeBuilder
{
    #[Righteous(RedundantNestedFrom::class)]
    public function fromModel(object $model): ReadyBadgeStrip
    {
        return ReadyBadgeStrip::from(['badge' => BadgeCopy::from($model)]);
    }

    #[Righteous(RedundantNestedFrom::class)]
    public function fromReady(BadgeCopy $ready): ReadyBadgeStrip
    {
        return ReadyBadgeStrip::from(['badge' => $ready]);
    }
}
