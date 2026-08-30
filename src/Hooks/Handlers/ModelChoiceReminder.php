<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Asks for a model whenever an agent is dispatched without one — the agent inherits the dispatcher's,
 * so the expensive answer is the default and the cheap one must be chosen. It WARNS and lets the
 * dispatch through: the judgement is the dispatcher's, being the only party holding the task.
 */
final class ModelChoiceReminder extends Hook
{
    /**
     * What the choice is between, cheapest first, each with the work it is actually for.
     */
    private const string SCALE =
        'haiku for mechanical work with a known answer (run this, read that file, list what matches); '
        . 'sonnet for ordinary work that needs care but no invention; '
        . 'opus only where the task turns on judgement — a design call, a review, an ambiguous failure.';

    public function summary(): string
    {
        return 'Asks for an explicit model when an agent is dispatched without one, since an unnamed model inherits the dispatcher\'s.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreToolUse', 'Agent')];
    }

    /**
     * Before the spend, never after: once the agent is running the money is gone, and a nudge that
     * arrives then can change nothing about the call it is describing.
     */
    protected function onPreToolUse(HookEvent $event): int
    {
        if ($event->modelRequested() !== '') {
            return $this->pass();
        }

        return $this->quietly($event, sprintf(
            'Code Commandments — this dispatch names no model, so `%s` will run on YOURS. Say what the '
                . 'task actually demands and pass the cheapest model that meets it: %s If you have already '
                . 'judged this one and your model is the right one, carry on — the point is that it be a '
                . 'decision rather than a default.',
            $event->agentTypeRequested() === '' ? 'the agent' : $event->agentTypeRequested(),
            self::SCALE,
        ));
    }
}
