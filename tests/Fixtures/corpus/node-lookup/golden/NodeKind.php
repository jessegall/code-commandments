<?php

namespace App\NodeLookup;

/**
 * The closed set of node kinds a workflow graph can hold.
 */
enum NodeKind: string
{
    /** Entry node that starts a run when its trigger fires. */
    case Trigger = 'trigger';

    /** Performs work and produces an output the next node consumes. */
    case Action = 'action';

    /** Routes the run down one branch based on a condition. */
    case Branch = 'branch';

    public function canStartRun(): bool
    {
        return match ($this) {
            NodeKind::Trigger => true,
            NodeKind::Action, NodeKind::Branch => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            NodeKind::Trigger => 'Trigger',
            NodeKind::Action => 'Action',
            NodeKind::Branch => 'Branch',
        };
    }
}
