<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferClassifierCompositionProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferClassifierCompositionProphetTest extends TestCase
{
    private function judge(string $body): Judgment
    {
        // classifier_base = short name 'Classifier' so the name-based fallback
        // (no index in this unit) recognises `*Classifier` receivers by their base.
        $prophet = (new PreferClassifierCompositionProphet)->configure(['classifier_base' => 'Classifier']);
        $src = "<?php\nnamespace App;\n"
            . "abstract class Classifier {}\n"
            . "class DateTimeClassifier extends Classifier { public static function make(): static { return new static; } public function matches(\$x): bool { return true; } }\n"
            . "class EnumClassifier extends Classifier { public static function make(): static { return new static; } public function matches(\$x): bool { return true; } }\n"
            . "class C {\n{$body}\n}\n";

        return $prophet->judge('/x.php', $src);
    }

    public function test_flags_an_or_chain_of_classifier_matches(): void
    {
        $j = $this->judge("public function f(\$t): bool { return DateTimeClassifier::make()->matches(\$t) || EnumClassifier::make()->matches(\$t); }");

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('anyOf', $j->warnings[0]->message);
    }

    public function test_flags_an_and_chain_suggesting_allOf(): void
    {
        $j = $this->judge("public function f(\$t): bool { return DateTimeClassifier::make()->matches(\$t) && EnumClassifier::make()->matches(\$t); }");

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('allOf', $j->warnings[0]->message);
    }

    public function test_leaves_a_single_classifier_match(): void
    {
        $this->assertTrue($this->judge("public function f(\$t): bool { return DateTimeClassifier::make()->matches(\$t); }")->isRighteous());
    }

    public function test_leaves_matches_on_different_arguments(): void
    {
        // Not composable into one classifier check — different subjects.
        $this->assertTrue($this->judge("public function f(\$a, \$b): bool { return DateTimeClassifier::make()->matches(\$a) || EnumClassifier::make()->matches(\$b); }")->isRighteous());
    }

    public function test_leaves_non_classifier_matches(): void
    {
        // `->matches()` on a non-classifier (a string/validator helper) is unrelated.
        $j = $this->judge("public function f(\$t): bool { \$s = new \\App\\Str; return \$s->matches(\$t) || \$s->matches(\$t); }");
        $this->assertTrue($j->isRighteous());
    }
}
