<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * A workflow node kind that owns its own factory, render and validate behaviour
 * — the single typed dispatch that replaces three parallel match-on-string ladders.
 */
interface NodeKind
{
    /** The stable key this kind is registered and serialised under. */
    public function key(): string;

    /** The default label a freshly dropped node of this kind carries. */
    public function buildLabel(): string;

    /** The editor glyph used to render a node of this kind on the canvas. */
    public function render(): string;

    /**
     * The config keys a node of this kind requires to be runnable; empty when valid.
     *
     * @return list<string>
     */
    public function missingConfig(Fluent $config): array;
}
