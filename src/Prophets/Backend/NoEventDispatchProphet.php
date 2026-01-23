<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: Dispatch events with event() - Not Event::dispatch(...).
 */
class NoEventDispatchProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use event() helper instead of Event::dispatch() or static dispatch';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use the event() helper function to dispatch events.

Never use Event::dispatch() or MyEvent::dispatch() static methods.
The event() helper is more readable and consistent.

Bad:
    UserCreatedEvent::dispatch($user);
    Event::dispatch(new UserCreatedEvent($user));

Good:
    event(new UserCreatedEvent($user));
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(fn (PhpContext $ctx) => $this->findEventDispatchCalls($ctx))
            ->sinsFromMatches(
                'Using static ::dispatch() on event class',
                'Use event() helper instead: event(new EventClass(...))'
            )
            ->judge();
    }

    private function findEventDispatchCalls(PhpContext $ctx): PhpContext
    {
        $matches = [];
        $lines = explode("\n", $ctx->content);

        foreach ($lines as $lineNum => $line) {
            // Skip comments and docblocks
            if (preg_match('/^\s*(\/\/|\*|#)/', $line)) {
                continue;
            }

            // Skip strings (quoted content)
            if (preg_match('/[\'"].*::dispatch.*[\'"]/', $line)) {
                continue;
            }

            // Look for static dispatch calls on event classes
            if (preg_match('/[A-Z]\w+::dispatch\s*\(/', $line)) {
                // Allow Bus::dispatch, Queue::dispatch, etc.
                if (preg_match('/(Bus|Queue|Job|Notification)::dispatch/', $line)) {
                    continue;
                }

                // Check if it looks like an event class (ends with Event or similar)
                if (preg_match('/(\w+Event|Event\w*)::dispatch/', $line)) {
                    $matches[] = new MatchResult(
                        name: 'event_dispatch',
                        pattern: '',
                        match: '',
                        line: $lineNum + 1,
                        offset: null,
                        content: trim($line),
                        groups: [],
                    );
                }
            }
        }

        return $ctx->with(matches: $matches);
    }
}
