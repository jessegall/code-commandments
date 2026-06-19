<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class RegistryReturnContractProphetTest extends TestCase
{
    private RegistryReturnContractProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new RegistryReturnContractProphet;
    }

    public function test_flags_an_option_getter_on_a_marked_registry(): void
    {
        $judgment = $this->judge('class R implements Registry { public function pipeline(string $c): Option { return $this->p($c); } }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('returns an Option', $judgment->sins[0]->message);
    }

    public function test_flags_a_nullable_getter_on_an_attribute_marked_registry(): void
    {
        $judgment = $this->judge('#[Registry] class R { public function tag(string $k): ?Tag { return $this->tags[$k] ?? null; } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_leaves_finder_named_getters(): void
    {
        $this->assertTrue($this->judge('class R implements Registry { public function findByEmail(string $e): ?User { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function tryGet(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function tagOrNull(string $k): ?T { return null; } }')->isRighteous());
    }

    public function test_leaves_unmarked_classes_and_non_public_or_bool_methods(): void
    {
        $this->assertTrue($this->judge('class Plain { public function get(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { private function memo(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function has(string $k): bool { return true; } }')->isRighteous());
    }

    public function test_repent_retypes_and_wraps_an_option_getter(): void
    {
        $src = "<?php\nclass R implements Registry {\n /** @return Option<PipelineSpec> */\n public function pipeline(string \$c): Option { return \$this->resolve(\$c); }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public function pipeline(string $c): PipelineSpec', $result->newContent);
        $this->assertStringContainsString('return ($this->resolve($c))->getOrThrow();', $result->newContent);
    }

    public function test_repent_retypes_and_throws_for_a_nullable_getter(): void
    {
        $src = "<?php\nclass R implements Registry {\n public function tag(string \$k): ?Tag { return \$this->tags[\$k] ?? null; }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public function tag(string $k): Tag', $result->newContent);
        $this->assertStringContainsString('?? throw new \\RuntimeException(', $result->newContent);
        $this->assertStringNotContainsString('?? null ??', $result->newContent);
        $this->assertNotFalse((new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($result->newContent));
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\ninterface Registry {}\n" . $body);
    }
}
