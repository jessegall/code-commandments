<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Workspace;

use JesseGall\PhpTypes\Option;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Raises one orchestration moment to every {@see Trigger} the project wrote and merges what they said
 * into the single {@see Verdict} the caller acts on — reading each trigger's moment off its signature
 * ({@see momentOf}), standing a refusal only where the act has not happened yet ({@see answer}), and
 * containing a trigger that cannot answer so it is passed over by itself while the rest still run.
 */
final class Triggers
{
    /**
     * How long one trigger may take before the project is told about it, in nanoseconds. It is not a
     * timeout — a trigger is not killed mid-answer — but a moment is raised inside a command a person is
     * waiting on, so a trigger that costs a second should not be able to do it unremarked.
     */
    private const int SLOW = 250_000_000;

    /**
     * @param  list<Trigger>  $triggers
     * @param  resource  $err  where a trigger that could not answer is NAMED — stderr for a person, since
     *                         that is the stream a harness surfaces, and anything writable for a test. A
     *                         layer that reports its own defects only in principle is one that goes quiet.
     */
    public function __construct(private readonly array $triggers, private $err = STDERR) {}

    /**
     * The triggers armed for THIS session — the ones the profile in force carries, and none at all when
     * no profile is in force. A session that is not orchestrating loads nothing, so the question of
     * whether a build's rule should fire there never arises.
     */
    public static function inSession(Workspace $workspace): self
    {
        $running = Instance::inSession($workspace)->profile();

        foreach ($running as $name) {
            foreach (Profiles::of($workspace)->named($name) as $profile) {
                return new self($profile->triggers());
            }
        }

        return new self([]);
    }

    /**
     * What the project says about $event, as one answer.
     */
    public function dispatch(Event $event): Verdict
    {
        $verdicts = [];

        foreach ($this->triggers as $trigger) {
            $verdicts[] = $this->ask($trigger, $event);
        }

        return Verdict::merge($verdicts);
    }

    /**
     * What one trigger says about $event — silence when this is not its moment, when it is written wrong,
     * or when it threw.
     */
    private function ask(Trigger $trigger, Event $event): Verdict
    {
        foreach ($this->momentOf($trigger) as $moment) {
            return $event instanceof $moment ? $this->answer($trigger, $event) : Verdict::pass();
        }

        return Verdict::pass();
    }

    /**
     * Run the trigger and take its verdict, demoted where the moment cannot be stopped — a refusal stands
     * only on a {@see Vetoable}; anywhere else the act is already done, so the reason survives as a quiet
     * note and only its force is taken away. A trigger that throws, or answers with something that is not
     * a verdict, passes for ITSELF alone and is named: one bad project class silencing every trigger in
     * the process — the package's own refusals for the moment included — is what this prevents.
     */
    private function answer(Trigger $trigger, Event $event): Verdict
    {
        $started = hrtime(true);

        try {
            $verdict = $trigger->fire($event); // @phpstan-ignore-line the signature is the subclass's; {@see momentOf} proved it takes this event
        } catch (Throwable $thrown) {
            $threw = $thrown::class;

            return $this->defect($trigger, "threw {$threw}: {$thrown->getMessage()} — it answered for nothing, and every other trigger ran");
        } finally {
            $this->timed($trigger, hrtime(true) - $started);
        }

        if (! $verdict instanceof Verdict) {
            return $this->defect($trigger, 'answered with something that is not a Verdict');
        }

        return $event instanceof Vetoable ? $verdict : $verdict->demoted();
    }

    /**
     * WHICH moment $trigger handles — the class its `fire()` types its first parameter as. A trigger
     * with no `handle`, or one whose parameter is untyped, builtin, or not an {@see Event}, is not
     * silently skipped: it is named, because a tie-in that never fires and never says why is
     * indistinguishable from one nothing happened for.
     *
     * @return Option<class-string<Event>>
     */
    private function momentOf(Trigger $trigger): Option
    {
        if (! method_exists($trigger, 'fire')) {
            $this->defect($trigger, 'declares no fire() — a trigger says which moment it wants by type-hinting one');

            return Option::none();
        }

        $type = (new ReflectionMethod($trigger, 'fire')->getParameters()[0] ?? null)?->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin() || ! is_a($type->getName(), Event::class, true)) {
            $this->defect($trigger, 'fire() must type-hint the moment it handles — an orchestration Event');

            return Option::none();
        }

        return Option::some($type->getName());
    }

    /**
     * Say that a trigger took long enough to be worth mentioning. It is said where it happened rather
     * than counted somewhere, so the line names the trigger a person would otherwise go looking for.
     */
    private function timed(Trigger $trigger, int $elapsed): void
    {
        if ($elapsed < self::SLOW) {
            return;
        }

        $this->say($trigger, sprintf('took %.1fs — a moment is raised inside a command somebody is waiting on', $elapsed / 1_000_000_000));
    }

    /**
     * Name a trigger that could not answer, and pass for it. The verdict is the return so a caller reads
     * "this is what it said" rather than remembering to pass afterwards.
     */
    private function defect(Trigger $trigger, string $reason): Verdict
    {
        $this->say($trigger, $reason);

        return Verdict::pass();
    }

    private function say(Trigger $trigger, string $reason): void
    {
        $name = $trigger::class;

        fwrite($this->err, "code-commandments: {$name} {$reason}\n");
    }
}
