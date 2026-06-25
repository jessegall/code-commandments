<?php

declare(strict_types=1);

namespace App\Clean;

/**
 * Companion to ManyFlaggedCases — every method here is a legitimate shape the
 * prophet must STAY SILENT on. The test asserts zero warnings, guarding against
 * false positives.
 */
class CleanCases
{
    // Already inside the record: $this writes are the destination, not the smell.
    public function ownBehaviour(): void
    {
        $this->edit_seq = $this->edit_seq + 1;
        $this->status = Status::Active;
        $this->save();
    }

    // A save() with no preceding attribute write on the same instance.
    public function bareSave($m): void
    {
        $this->prepare();
        $m->save();
    }

    // An unrelated statement separates the write from the save.
    public function separated($m): void
    {
        $m->status = Status::Active;
        $this->audit($m);
        $m->save();
    }

    // The save target differs from the written variable.
    public function differentVar($a, $b): void
    {
        $a->status = Status::Active;
        $b->save();
    }

    // A method call result is assigned then saved — not an attribute write.
    public function localAssign($m): void
    {
        $result = $m->compute();
        $m->save();
    }

    // Writing to a nested/array offset, not a direct own-attribute.
    public function arrayOffsetWrite($m): void
    {
        $m->meta['key'] = 'value';
        $m->save();
    }

    // A fluent chain, not a property assignment.
    public function fluentChain($query): void
    {
        $query->where('active', true)->update(['x' => 1]);
        $query->save();
    }

    // No persist call at all.
    public function noSave($m): void
    {
        $m->status = Status::Active;
        $m->touched = true;
    }

    // save() called on a property chain ($this->x), not a plain variable — the
    // behaviour belongs on whatever owns it, but this conservative detector only
    // fires on plain non-$this variables to keep false positives near zero.
    public function saveOnPropertyChain(): void
    {
        $this->order->status = Status::Active;
        $this->order->save();
    }
}
