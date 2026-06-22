<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\AnchorEnumComparisonProphet;
use JesseGall\CodeCommandments\Results\Warning;
use PHPUnit\Framework\TestCase;

class AnchorEnumComparisonProphetTest extends TestCase
{
    private AnchorEnumComparisonProphet $prophet;

    private string $content;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new AnchorEnumComparisonProphet();
        $this->path = __DIR__ . '/../Fixtures/Backend/Sinful/AnchorEnumComparison/Sample.php';
        $this->content = file_get_contents($this->path);
    }

    /** @return list<Warning> */
    private function warnings(): array
    {
        return array_values($this->prophet->judge($this->path, $this->content)->warnings);
    }

    public function test_flags_exactly_the_non_nullable_subjects(): void
    {
        $warnings = $this->warnings();

        // flagParam, flagThisProp, flagVarProp — and nothing else.
        $this->assertCount(3, $warnings);
    }

    public function test_each_finding_suggests_the_instance_anchor(): void
    {
        $messages = implode("\n", array_map(fn (Warning $w) => $w->message, $this->warnings()));

        $this->assertStringContainsString('$s->equalsAny(Status::A, Status::B)', $messages);
        $this->assertStringContainsString('$this->status->equalsAny(Status::A, Status::B)', $messages);
        $this->assertStringContainsString('$d->type->equalsAny(Status::A, Status::B)', $messages);
    }

    public function test_leaves_nullable_subjects_and_singular_helpers(): void
    {
        $messages = implode("\n", array_map(fn (Warning $w) => $w->message, $this->warnings()));

        // The nullable-subject and singular-equals call sites must not be flagged.
        $this->assertStringNotContainsString('$d->maybe', $messages);
        $this->assertStringNotContainsString('::equals(', $messages);
    }

    public function test_findings_are_auto_fixable(): void
    {
        foreach ($this->warnings() as $warning) {
            $this->assertTrue($warning->autoFixable);
        }
    }

    public function test_repent_rewrites_to_the_instance_form(): void
    {
        $result = $this->prophet->repent($this->path, $this->content);

        $this->assertTrue($result->absolved);
        $code = (string) $result->newContent;

        $this->assertStringContainsString('return $s->equalsAny(Status::A, Status::B);', $code);
        $this->assertStringContainsString('return $this->status->equalsAny(Status::A, Status::B);', $code);
        $this->assertStringContainsString('return $d->type->equalsAny(Status::A, Status::B);', $code);

        // Untouched call sites stay as the static form.
        $this->assertStringContainsString('return Status::equalsAny($s, Status::A, Status::B);', $code); // nullable param
        $this->assertStringContainsString('return Status::equals($s, Status::A);', $code); // singular
    }
}
