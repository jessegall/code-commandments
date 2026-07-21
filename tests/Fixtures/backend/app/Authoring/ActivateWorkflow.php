<?php

namespace Shop\Authoring;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
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
