<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Accepting;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Event;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Trigger;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Triggers;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Reported;
use JesseGall\CodeCommandments\Cli\Orchestration\Events\Verdict;
use JesseGall\CodeCommandments\Cli\Orchestration\Hold;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The rules a handler author is otherwise trusted with, which is to say the ones that must live in the
 * dispatcher: which moment a handler hears is its SIGNATURE, a refusal stands only where the act has not
 * happened yet, and a handler that cannot answer is passed over by itself while everything else runs.
 */
final class TriggersTest extends TestCase
{
    /**
     * @var resource
     */
    private $err;

    protected function setUp(): void
    {
        $this->err = fopen('php://memory', 'r+');
    }

    public function test_a_trigger_hears_the_moment_its_signature_names_and_no_other(): void
    {
        $handlers = new Triggers([
            new class extends Trigger
            {
                public function fire(Reported $event): Verdict
                {
                    return Verdict::note('the reporter spoke');
                }
            },
            new class extends Trigger
            {
                public function fire(Accepting $event): Verdict
                {
                    return Verdict::note('the accepter spoke');
                }
            },
        ], $this->err);

        $said = $handlers->dispatch($this->reported())->message()->unwrapOr('');

        $this->assertSame('the reporter spoke', $said, 'only the handler typed for this moment ran');
    }

    public function test_a_trigger_typed_for_the_base_event_hears_every_moment(): void
    {
        $handlers = new Triggers([
            new class extends Trigger
            {
                public function fire(Event $event): Verdict
                {
                    return Verdict::note('heard ' . $event->item());
                }
            },
        ], $this->err);

        $this->assertSame('heard shipping', $handlers->dispatch($this->reported())->message()->unwrapOr(''));
        $this->assertSame('heard shipping', $handlers->dispatch($this->accepting())->message()->unwrapOr(''));
    }

    public function test_a_refusal_stands_on_a_moment_that_has_not_happened_yet(): void
    {
        $verdict = new Triggers([$this->refusing()], $this->err)->dispatch($this->accepting());

        $this->assertSame('nothing measured it', $verdict->refusal()->unwrapOr(''), 'Accepting is raised before the board moves, so a veto is worth something');
    }

    public function test_a_refusal_on_a_moment_already_true_is_demoted_and_never_dropped(): void
    {
        // A receipt is on disk by the time `Reported` is raised. Refusing it would be theatre — but the
        // handler still SAW something, so the reason travels as a quiet note rather than vanishing.
        $verdict = new Triggers([$this->refusing()], $this->err)->dispatch($this->reported());

        $this->assertTrue($verdict->refusal()->isNone(), 'nothing can be stopped here');
        $this->assertSame('nothing measured it', $verdict->message()->unwrapOr(''), 'the reason survives');
        $this->assertTrue($verdict->response->suppressOutput, 'demoted to a QUIET note');
    }

    public function test_a_trigger_that_throws_passes_for_itself_alone_and_is_named(): void
    {
        $thrower = new class extends Trigger
        {
            public function fire(Reported $event): Verdict
            {
                throw new RuntimeException('the project class is broken');
            }
        };

        $verdict = new Triggers([
            $thrower,
            new class extends Trigger
            {
                public function fire(Reported $event): Verdict
                {
                    return Verdict::note('and this one still ran');
                }
            },
        ], $this->err)->dispatch($this->reported());

        $this->assertSame('and this one still ran', $verdict->message()->unwrapOr(''), 'one bad class may not silence the rest');
        $this->assertStringContainsString($thrower::class, $this->stderr());
        $this->assertStringContainsString('the project class is broken', $this->stderr());
    }

    public function test_a_trigger_that_names_no_moment_is_named_rather_than_silently_never_firing(): void
    {
        $untyped = new class extends Trigger
        {
            public function fire($event): Verdict // @phpstan-ignore-line deliberately written wrong
            {
                return Verdict::refuse('never reached');
            }
        };

        $verdict = new Triggers([$untyped], $this->err)->dispatch($this->accepting());

        $this->assertTrue($verdict->isSilent());
        $this->assertStringContainsString($untyped::class, $this->stderr());
        $this->assertStringContainsString('type-hint the moment', $this->stderr());
    }

    public function test_a_trigger_with_no_fire_at_all_is_named(): void
    {
        $empty = new class extends Trigger {};

        $verdict = new Triggers([$empty], $this->err)->dispatch($this->accepting());

        $this->assertTrue($verdict->isSilent());
        $this->assertStringContainsString('declares no fire()', $this->stderr());
    }

    /**
     * A handler that refuses whatever it is given — the same class on both moments, so the only thing
     * separating the two outcomes is the moment's own type.
     */
    private function refusing(): Trigger
    {
        return new class extends Trigger
        {
            public function fire(Event $event): Verdict
            {
                return Verdict::refuse('nothing measured it');
            }
        };
    }

    private function reported(): Reported
    {
        return new Reported('/nowhere', $this->claim(), Option::none());
    }

    private function accepting(): Accepting
    {
        return new Accepting('/nowhere', $this->claim(), Option::none());
    }

    private function claim(): Claim
    {
        return new Claim('shipping', new Hold('worker-a', '10:00'), Stage::Reported);
    }

    /**
     * What the dispatcher wrote about a handler that could not answer.
     */
    private function stderr(): string
    {
        rewind($this->err);

        return (string) stream_get_contents($this->err);
    }
}
