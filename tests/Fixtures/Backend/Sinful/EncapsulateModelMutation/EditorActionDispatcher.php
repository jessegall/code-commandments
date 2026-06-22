<?php

declare(strict_types=1);

namespace App\Workflow\Realtime;

/**
 * Fixture for #192: the reported anemic-model mutation — a self-referential
 * counter bumped at the call site and immediately persisted, duplicated across
 * two call sites before anyone noticed. The behaviour belongs on the model as
 * `Workflow::incrementSequenceNumber()`.
 */
class EditorActionDispatcher
{
    public function dispatch(Workflow $workflow): void
    {
        $workflow->edit_seq = $workflow->edit_seq + 1;
        $workflow->save();
    }

    public function ship(Order $order): void
    {
        $order->status = OrderStatus::Shipped;
        $order->save();
    }

    public function verify(User $user): void
    {
        $user->verified_at = now();
        $user->verification_token = null;
        $user->save();
    }
}
