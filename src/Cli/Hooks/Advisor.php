<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use Closure;
use Throwable;

/**
 * How a journal moment is answered without keeping the agent waiting: the gates answer now, and the advising
 * hooks are asked in a detached child that tells the agent through the journal's queue. Where the journal
 * named no queue, every hook answers now, as it always did.
 */
final readonly class Advisor
{
    public function __construct(private JournalHook $hook) {}

    /**
     * The answer to give now — the gates' alone where the journal named a queue for the advice.
     *
     * @param  array<string, mixed>  $given
     */
    public function answerNow(array $given): JournalAnswer
    {
        $queued = JournalQueue::fromEnvironment()->isSome();

        return self::quietly(fn () => $queued ? $this->hook->gateAnswerFor($given) : $this->hook->answerFor($given));
    }

    /**
     * Ask the advising hooks about the moment in $given and tell the agent what they say through the queue —
     * in a forked child that leaves its parent's session and output, so whoever waits on the answer already
     * given (the journal, reading a spawned hook to its end) is not kept waiting. Nothing where the journal
     * named no queue.
     *
     * @param  array<string, mixed>  $given
     */
    public function adviseLater(array $given, string $home): void
    {
        JournalQueue::fromEnvironment()->inspect(function (JournalQueue $queue) use ($given, $home): void {
            self::reap();

            $child = function_exists('pcntl_fork') ? pcntl_fork() : -1;

            if ($child > 0) {
                return;
            }

            if ($child === 0) {
                self::detach();
            }

            chdir($home);
            $queue->tell(self::quietly(fn () => $this->hook->adviceFor($given)));

            if ($child === 0 && function_exists('posix_kill')) {
                // The child holds copies of the parent's warm bridge processes. Ending it without running
                // their destructors keeps it from closing what the parent still reads through.
                posix_kill(posix_getpid(), SIGKILL);
            }
        });
    }

    /**
     * Leave the parent's session and let go of its output, so a reader of that output sees it end now.
     */
    private static function detach(): void
    {
        if (function_exists('posix_setsid')) {
            posix_setsid();
        }

        fclose(STDOUT);
        fclose(STDERR);
    }

    /**
     * The answer $ask gives, `{}` when it fails, so one bad moment never stops the rest — and anything a hook
     * printed goes to the log, never into the answer.
     *
     * @param  Closure(): JournalAnswer  $ask
     */
    private static function quietly(Closure $ask): JournalAnswer
    {
        ob_start();

        try {
            $answer = $ask();
        } catch (Throwable $failure) {
            @fwrite(STDERR, $failure . "\n");
            $answer = new JournalAnswer();
        }

        $stray = (string) ob_get_clean();

        if ($stray !== '') {
            @fwrite(STDERR, $stray);
        }

        return $answer;
    }

    /**
     * Collect every advice child that has finished, so none lingers as a zombie.
     */
    private static function reap(): void
    {
        while (function_exists('pcntl_waitpid') && pcntl_waitpid(-1, $status, WNOHANG) > 0) {
            continue;
        }
    }
}
