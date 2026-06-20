<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PassThroughDependencyProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PassThroughDependencyProphetTest extends TestCase
{
    private PassThroughDependencyProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PassThroughDependencyProphet;
    }

    public function test_flags_a_dependency_only_forwarded_to_one_collaborator(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function __construct(private Clock $clock, private Scheduler $sched) {}
            public function run(): void { $this->sched->at($this->clock); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('pass-through-dependency:clock', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('$this->sched', $judgment->warnings[0]->message);
    }

    public function test_flags_when_forwarded_several_times_to_the_same_collaborator(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function __construct(private Clock $clock, private Sched $s) {}
            public function run(): void { $this->s->at($this->clock); $this->s->tick($this->clock); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_dependency_used_directly(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
        class C {
            public function __construct(private Clock $clock, private Sched $s) {}
            public function run(): void { $this->clock->now(); $this->s->at($this->clock); }
        }
        PHP)->isRighteous());
    }

    public function test_does_not_flag_when_forwarded_to_multiple_collaborators(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
        class C {
            public function __construct(private Clock $clock, private A $a, private B $b) {}
            public function run(): void { $this->a->m($this->clock); $this->b->m($this->clock); }
        }
        PHP)->isRighteous());
    }

    public function test_does_not_flag_a_returned_or_unused_or_scalar_dependency(): void
    {
        $returned = $this->judge(<<<'PHP'
        class C { public function __construct(private Clock $clock) {} public function get() { return $this->clock; } }
        PHP);
        $this->assertTrue($returned->isRighteous(), 'a returned dep is exposed, not a relay');

        $unused = $this->judge(<<<'PHP'
        class C { public function __construct(private Clock $clock) {} public function run() { return 1; } }
        PHP);
        $this->assertTrue($unused->isRighteous(), 'an unused dep is a different smell');

        $scalar = $this->judge(<<<'PHP'
        class C { public function __construct(private int $n, private Sched $s) {} public function run() { $this->s->at($this->n); } }
        PHP);
        $this->assertTrue($scalar->isRighteous(), 'scalars are not injected collaborators');
    }

    public function test_does_not_choke_on_first_class_callable_calls(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function __construct(private Clock $clock, private Sched $s) {}
            public function run(): void { $fn = $this->s->at(...); $this->s->run($this->clock); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/tmp/x.php', "<?php\nnamespace App;\n" . $body);
    }
}
