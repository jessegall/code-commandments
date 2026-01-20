<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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
        // Skip validator files (they contain example code in heredocs)
        if (str_contains($filePath, 'Commandments/Validators/') || str_contains($filePath, 'Prophets/')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);

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
            // Pattern: SomeEvent::dispatch( or SomeEvent::dispatch(
            if (preg_match('/[A-Z]\w+::dispatch\s*\(/', $line)) {
                // Allow Bus::dispatch, Queue::dispatch, etc.
                if (preg_match('/(Bus|Queue|Job|Notification)::dispatch/', $line)) {
                    continue;
                }

                // Check if it looks like an event class (ends with Event or similar)
                if (preg_match('/(\w+Event|Event\w*)::dispatch/', $line)) {
                    $sins[] = $this->sinAt(
                        $lineNum + 1,
                        'Using static ::dispatch() on event class',
                        trim($line),
                        'Use event() helper instead: event(new EventClass(...))'
                    );
                }
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
