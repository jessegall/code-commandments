<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferAndThenProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferAndThenProphetTest extends TestCase
{
    private PreferAndThenProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferAndThenProphet;
    }

    public function test_flags_transform_then_get_or_none(): void
    {
        $judgment = $this->judge("return \$graph->nodeById(\$id)->transform(fn (\$n) => \$this->lookup(\$n))->getOr(Option::none());");

        $this->assertCount(1, $judgment->warnings);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
        $this->assertStringContainsString('andThen', $judgment->warnings[0]->message);
    }

    public function test_flags_map_then_get_or_self_none(): void
    {
        $judgment = $this->judge("return \$x->map(fn (\$n) => \$this->lookup(\$n))->getOr(self::none());");

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_real_default(): void
    {
        // getOr() with a real (non-none) default is a legitimate transform+fallback.
        $judgment = $this->judge("return \$x->transform(fn (\$n) => strtoupper(\$n))->getOr('default');");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_get_or_none_without_a_preceding_map(): void
    {
        // A bare getOr(none()) (no transform/map before it) is too ambiguous to flag.
        $judgment = $this->judge("return \$x->getOr(Option::none());");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_repent_collapses_to_and_then(): void
    {
        $src = "<?php\nreturn \$graph->nodeById(\$id)->transform(fn (\$n) => \$this->lookup(\$n))->getOr(Option::none());\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('->andThen(fn ($n) => $this->lookup($n))', $result->newContent);
        $this->assertStringNotContainsString('->getOr(', $result->newContent);
        $this->assertStringNotContainsString('->transform(', $result->newContent);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
