<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * The entry node that starts a workflow run when its event fires.
 */
final class TriggerKind implements NodeKind
{
    public function key(): string
    {
        return 'trigger';
    }

    public function buildLabel(): string
    {
        return 'When this happens';
    }

    public function render(): string
    {
        return '⚡';
    }

    public function missingConfig(Fluent $config): array
    {
        return $config->string('event') === '' ? ['event'] : [];
    }
}
