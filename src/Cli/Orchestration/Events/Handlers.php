<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Custom;
use JesseGall\PhpTypes\Option;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Raises one orchestration moment to every {@see Handler} the project wrote and merges what they said
 * into the single {@see Verdict} the caller acts on — reading each handler's moment off its signature
 * ({@see momentOf}), standing a refusal only where the act has not happened yet ({@see answer}), and
 * containing a handler that cannot answer so it is passed over by itself while the rest still run.
 */
final class Handlers
{
    /**
     * How long one handler may take before the project is told about it, in nanoseconds. It is not a
     * timeout — a handler is not killed mid-answer — but a moment is raised inside a command a person is
     * waiting on, so a handler that costs a second should not be able to do it unremarked.
     */
    private const int SLOW = 250_000_000;

    /**
     * @param  list<Handler>  $handlers
     * @param  resource  $err  where a handler that could not answer is NAMED — stderr for a person, since
     *                         that is the stream a harness surfaces, and anything writable for a test. A
     *                         layer that reports its own defects only in principle is one that goes quiet.
     */
    public function __construct(private readonly array $handlers, private $err = STDERR) {}

    /**
     * The handlers the project at $root has, minus any it silenced with `$config->disable(...)` — the same
     * verb that turns off a rule or a hook, through the same filter.
     */
    public static function forProject(string $root): self
    {
        return new self(Config::load($root)->enabled(Custom::handlers($root)));
    }

    /**
     * What the project says about $event, as one answer.
     */
    public function dispatch(Event $event): Verdict
    {
        $verdicts = [];

        foreach ($this->handlers as $handler) {
            $verdicts[] = $this->ask($handler, $event);
        }

        return Verdict::merge($verdicts);
    }

    /**
     * What one handler says about $event — silence when this is not its moment, when it is written wrong,
     * or when it threw.
     */
    private function ask(Handler $handler, Event $event): Verdict
    {
        foreach ($this->momentOf($handler) as $moment) {
            return $event instanceof $moment ? $this->answer($handler, $event) : Verdict::pass();
        }

        return Verdict::pass();
    }

    /**
     * Run the handler and take its verdict, demoted where the moment cannot be stopped — a refusal stands
     * only on a {@see Vetoable}; anywhere else the act is already done, so the reason survives as a quiet
     * note and only its force is taken away. A handler that throws, or answers with something that is not
     * a verdict, passes for ITSELF alone and is named: one bad project class silencing every handler in
     * the process — the package's own refusals for the moment included — is what this prevents.
     */
    private function answer(Handler $handler, Event $event): Verdict
    {
        $started = hrtime(true);

        try {
            $verdict = $handler->handle($event); // @phpstan-ignore-line the signature is the subclass's; {@see momentOf} proved it takes this event
        } catch (Throwable $thrown) {
            $threw = $thrown::class;

            return $this->defect($handler, "threw {$threw}: {$thrown->getMessage()} — it answered for nothing, and every other handler ran");
        } finally {
            $this->timed($handler, hrtime(true) - $started);
        }

        if (! $verdict instanceof Verdict) {
            return $this->defect($handler, 'answered with something that is not a Verdict');
        }

        return $event instanceof Vetoable ? $verdict : $verdict->demoted();
    }

    /**
     * WHICH moment $handler handles — the class its `handle()` types its first parameter as. A handler
     * with no `handle`, or one whose parameter is untyped, builtin, or not an {@see Event}, is not
     * silently skipped: it is named, because a tie-in that never fires and never says why is
     * indistinguishable from one nothing happened for.
     *
     * @return Option<class-string<Event>>
     */
    private function momentOf(Handler $handler): Option
    {
        if (! method_exists($handler, 'handle')) {
            $this->defect($handler, 'declares no handle() — a handler says which moment it wants by type-hinting one');

            return Option::none();
        }

        $type = (new ReflectionMethod($handler, 'handle')->getParameters()[0] ?? null)?->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin() || ! is_a($type->getName(), Event::class, true)) {
            $this->defect($handler, 'handle() must type-hint the moment it handles — an orchestration Event');

            return Option::none();
        }

        return Option::some($type->getName());
    }

    /**
     * Say that a handler took long enough to be worth mentioning. It is said where it happened rather
     * than counted somewhere, so the line names the handler a person would otherwise go looking for.
     */
    private function timed(Handler $handler, int $elapsed): void
    {
        if ($elapsed < self::SLOW) {
            return;
        }

        $this->say($handler, sprintf('took %.1fs — a moment is raised inside a command somebody is waiting on', $elapsed / 1_000_000_000));
    }

    /**
     * Name a handler that could not answer, and pass for it. The verdict is the return so a caller reads
     * "this is what it said" rather than remembering to pass afterwards.
     */
    private function defect(Handler $handler, string $reason): Verdict
    {
        $this->say($handler, $reason);

        return Verdict::pass();
    }

    private function say(Handler $handler, string $reason): void
    {
        $name = $handler::class;

        fwrite($this->err, "code-commandments: {$name} {$reason}\n");
    }
}
