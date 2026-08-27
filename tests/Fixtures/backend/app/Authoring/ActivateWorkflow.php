<?php

namespace Shop\Authoring;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The command answers with a row it does not actually have. `updatedAt: ''` is not a timestamp — the
 * caller never read one — so every consumer that renders "last updated" prints a blank and no type
 * can tell it the value was manufactured.
 */
final class ActivateWorkflow
{
    #[Sinful(PlaceholderFilledData::class)]
    public function activate(string $slug): WorkflowRowData
    {
        return new WorkflowRowData(slug: $slug, name: $slug, trigger: null, active: true, updatedAt: '');
    }
}

/**
 * The FIX for {@see ActivateWorkflow}: the required `updatedAt` slot is handed the REAL value — the
 * command holds the clock that owns the timestamp and reads it, instead of filling the promise with
 * `''` to satisfy the signature.
 */
#[Fixed(PlaceholderFilledData::class)]
final class StampedActivateWorkflow
{
    public function __construct(private readonly WorkflowClock $clock) {}

    public function activate(string $slug): WorkflowRowData
    {
        return new WorkflowRowData(
            slug: $slug,
            name: $slug,
            trigger: null,
            active: true,
            updatedAt: $this->clock->stamp(),
        );
    }
}

/**
 * The source of the timestamp the fixed activation asks for.
 */
#[Fixed(PlaceholderFilledData::class)]
final class WorkflowClock
{
    public function stamp(): string
    {
        return '2024-01-01T00:00:00Z';
    }
}
