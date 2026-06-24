<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * A node that performs an effect — calls a tool, sends a message, writes a record.
 */
final class ActionKind implements NodeKind
{
    public function key(): string
    {
        return 'action';
    }

    public function buildLabel(): string
    {
        return 'Do this';
    }

    public function render(): string
    {
        return '▶';
    }

    public function missingConfig(Fluent $config): array
    {
        return $config->string('tool') === '' ? ['tool'] : [];
    }
}
