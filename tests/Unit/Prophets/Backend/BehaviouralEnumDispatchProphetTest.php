<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\BehaviouralEnumDispatchProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class BehaviouralEnumDispatchProphetTest extends TestCase
{
    private BehaviouralEnumDispatchProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new BehaviouralEnumDispatchProphet;
    }

    public function test_flags_a_wide_behavioural_enum_match(): void
    {
        $judgment = $this->judge('class C { public function emit($g, $n, $d) { return match ($d->kind) {
            NodeKind::Trigger => $this->triggerEmitter->emit($g, $n, $d),
            NodeKind::Pipe => $this->pipeEmitter->emit($g, $n, $d),
            NodeKind::Pipeline => $this->pipelineEmitter->emit($g, $n, $d),
            NodeKind::Control => $this->emitControl($g, $n, $d),
            NodeKind::Input => $this->inputEmitter->emit($g, $n, $d),
            NodeKind::Output => $this->outputEmitter->emit($g, $n, $d),
        }; } }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('strategy object', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NodeKind', $judgment->warnings[0]->message);
        // #92: steer to a dedicated injected provider (for($key): Strategy), not
        // an inline map, and away from *Resolver/*Factory naming.
        $this->assertStringContainsString('for($key): Strategy', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NOT a `*Resolver`', $judgment->warnings[0]->message);
    }

    public function test_leaves_a_value_only_map_to_prefer_type_method(): void
    {
        $this->assertTrue($this->judge('class C { public function n($c) { return match ($c) {
            Color::Red => 1, Color::Green => 2, Color::Blue => 3, Color::Black => 4, Color::White => 5,
        }; } }')->isRighteous());
    }

    public function test_leaves_a_small_match(): void
    {
        $this->assertTrue($this->judge('class C { public function a($x, $d) { return match ($x) {
            E::A => $d->a(), E::B => $d->b(), E::C => $d->c(),
        }; } }')->isRighteous());
    }

    public function test_leaves_a_match_true_guard(): void
    {
        $this->assertTrue($this->judge('class C { public function a($x, $d) { return match (true) {
            $x->a() => $d->a(), $x->b() => $d->b(), $x->c() => $d->c(), $x->d() => $d->d(), $x->e() => $d->e(),
        }; } }')->isRighteous());
    }

    public function test_leaves_dispatch_inside_the_enums_own_file(): void
    {
        // A match in the enum's own file is the destination, not a smell.
        $src = "<?php\nnamespace App;\nenum E: string { case A='a'; case B='b'; case C='c'; case D='d'; case E='e';\n public function run(\$d) { return match (\$this) { E::A => \$d->a(), E::B => \$d->b(), E::C => \$d->c(), E::D => \$d->d(), E::E => \$d->e() }; } }\n";

        $this->assertTrue($this->prophet->judge('/x.php', $src)->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n" . $body);
    }
}
