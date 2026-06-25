<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * A branch node that routes the run down one of two paths on a boolean test.
 */
final class ConditionKind implements NodeKind
{
    public function key(): string
    {
        return 'condition';
    }

    public function buildLabel(): string
    {
        return 'Only if';
    }

    public function render(): string
    {
        return '◆';
    }

    public function missingConfig(Fluent $config): array
    {
        return $config->string('expression') === '' ? ['expression'] : [];
    }
}
