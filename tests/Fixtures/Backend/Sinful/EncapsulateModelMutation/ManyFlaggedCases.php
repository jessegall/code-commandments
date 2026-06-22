<?php

declare(strict_types=1);

namespace App\Sinful;

/**
 * Exhaustive corpus for #192 — EVERY method below contains exactly one
 * mutate-then-save smell that EncapsulateModelMutationProphet must flag, across
 * assignment shapes (plain, compound, self-referential, increment/decrement,
 * enum), persist-call positions (method body, if/else, foreach, while, try,
 * closure, match arm), and variable kinds. The test asserts a 1:1 match between
 * the method count and the warning count, so a regression in either direction
 * (a missed case OR a new false positive) fails loudly.
 */
class ManyFlaggedCases
{
    // --- self-referential counters -------------------------------------------

    public function selfRefAdd($m): void
    {
        $m->edit_seq = $m->edit_seq + 1;
        $m->save();
    }

    public function compoundPlus($m): void
    {
        $m->count += 1;
        $m->save();
    }

    public function compoundMinus($m): void
    {
        $m->stock -= 1;
        $m->save();
    }

    public function compoundConcat($m): void
    {
        $m->log .= 'x';
        $m->save();
    }

    public function postIncrement($m): void
    {
        $m->version++;
        $m->save();
    }

    public function preIncrement($m): void
    {
        ++$m->revision;
        $m->save();
    }

    public function postDecrement($m): void
    {
        $m->remaining--;
        $m->save();
    }

    // --- closed-set / enum transitions ---------------------------------------

    public function enumStatus($order): void
    {
        $order->status = OrderStatus::Shipped;
        $order->save();
    }

    public function enumStateConst($job): void
    {
        $job->state = JobState::Running;
        $job->save();
    }

    // --- plain attribute writes ----------------------------------------------

    public function singlePlainWrite($user): void
    {
        $user->verified_at = 'now';
        $user->save();
    }

    public function multiFieldTransition($user): void
    {
        $user->verified_at = 'now';
        $user->verification_token = null;
        $user->save();
    }

    public function threeFieldTransition($invoice): void
    {
        $invoice->paid = true;
        $invoice->paid_at = 'now';
        $invoice->amount_due = 0;
        $invoice->save();
    }

    // --- inside control flow -------------------------------------------------

    public function insideIf($m, bool $flag): void
    {
        if ($flag) {
            $m->status = Status::Active;
            $m->save();
        }
    }

    public function insideElse($m, bool $flag): void
    {
        if ($flag) {
            // nothing
        } else {
            $m->status = Status::Inactive;
            $m->save();
        }
    }

    public function insideForeach($items): void
    {
        foreach ($items as $item) {
            $item->processed = true;
            $item->save();
        }
    }

    public function insideWhile($m): void
    {
        while (true) {
            $m->attempts += 1;
            $m->save();
        }
    }

    public function insideTry($m): void
    {
        try {
            $m->status = Status::Done;
            $m->save();
        } catch (\Throwable $e) {
            // swallow
        }
    }

    public function insideClosure($m): callable
    {
        return function () use ($m): void {
            $m->touched_at = 'now';
            $m->save();
        };
    }
}
