<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\EnumCaseMustBeDocumentedProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class EnumCaseMustBeDocumentedProphetTest extends TestCase
{
    private EnumCaseMustBeDocumentedProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new EnumCaseMustBeDocumentedProphet;
    }

    public function test_flags_an_undocumented_case_as_a_sin(): void
    {
        $judgment = $this->judge("enum Status { case Paid; }");

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('Status::Paid', $judgment->sins[0]->message);
    }

    public function test_flags_every_undocumented_case(): void
    {
        $judgment = $this->judge("enum Status { case A; case B; case C; }");

        $this->assertCount(3, $judgment->sins);
    }

    public function test_a_docblock_documents_a_case(): void
    {
        $judgment = $this->judge("enum Status {\n /** The thing is paid for. */\n case Paid; }");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_a_line_comment_documents_a_case_by_default(): void
    {
        $judgment = $this->judge("enum Status {\n // The thing is paid for.\n case Paid; }");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_a_block_comment_documents_a_case(): void
    {
        $judgment = $this->judge("enum Status {\n /* The thing is paid for. */\n case Paid; }");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_an_empty_docblock_does_not_count(): void
    {
        $judgment = $this->judge("enum Status {\n /** */\n case Paid; }");

        $this->assertCount(1, $judgment->sins);
    }

    public function test_a_separator_comment_does_not_count(): void
    {
        $judgment = $this->judge("enum Status {\n // --------\n case Paid; }");

        $this->assertCount(1, $judgment->sins);
    }

    public function test_docblock_mode_rejects_a_line_comment(): void
    {
        $prophet = (new EnumCaseMustBeDocumentedProphet)->configure(['style' => 'docblock']);

        $sinful = $prophet->judge('/x.php', "<?php\nenum Status {\n // documented enough\n case Paid; }");
        $this->assertCount(1, $sinful->sins);

        $clean = $prophet->judge('/x.php', "<?php\nenum Status {\n /** documented properly */\n case Paid; }");
        $this->assertTrue($clean->isRighteous());
    }

    public function test_backed_enum_cases_are_flagged_too(): void
    {
        $judgment = $this->judge("enum Status: string { case Paid = 'paid'; }");

        $this->assertCount(1, $judgment->sins);
    }

    public function test_documents_a_case_carrying_an_attribute(): void
    {
        // The doc comment sits above the attribute; it must still count.
        $judgment = $this->judge("enum Status {\n /** Paid in full. */\n #[SomeAttr]\n case Paid; }");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_touch_non_enum_constants(): void
    {
        $judgment = $this->judge("class A { const FOO = 1; }");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_the_sinful_fixture(): void
    {
        $path = __DIR__ . '/../../../Fixtures/Backend/Sinful/EnumCaseDocs/UndocumentedStatus.php';
        $judgment = $this->prophet->judge($path, (string) file_get_contents($path));

        $this->assertCount(3, $judgment->sins);
    }

    public function test_stays_silent_on_the_documented_fixture(): void
    {
        $path = __DIR__ . '/../../../Fixtures/Backend/Clean/EnumCaseDocs/DocumentedStatus.php';
        $judgment = $this->prophet->judge($path, (string) file_get_contents($path));

        $this->assertTrue($judgment->isRighteous(), 'documented fixture must produce no sins: '
            . implode(' | ', array_map(static fn ($s) => $s->message, $judgment->sins)));
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_class_docblock_see_bullets_document_the_cases(): void
    {
        $judgment = $this->judge(
            "/**\n"
            . " * The lifecycle of an order.\n"
            . " *\n"
            . " * - {@see Status::Paid}: payment captured; awaiting fulfilment.\n"
            . " * - {@see Status::Shipped}: handed to the carrier; tracking exists.\n"
            . " */\n"
            . "enum Status: string { case Paid = 'paid'; case Shipped = 'shipped'; }"
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_class_docblock_with_self_reference_documents_the_case(): void
    {
        $judgment = $this->judge(
            "/**\n * - {@see self::Paid}: payment captured; awaiting fulfilment.\n */\n"
            . "enum Status { case Paid; }"
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_class_docblock_missing_a_case_still_flags_that_case(): void
    {
        // Paid is documented in the class docblock; Shipped is not — only Shipped fires.
        $judgment = $this->judge(
            "/**\n * - {@see Status::Paid}: payment captured.\n */\n"
            . "enum Status { case Paid; case Shipped; }"
        );

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('Status::Shipped', $judgment->sins[0]->message);
    }

    public function test_bare_see_reference_without_a_description_does_not_count(): void
    {
        // A cross-reference with no description is not documentation.
        $judgment = $this->judge(
            "/**\n * See {@see Status::Paid} elsewhere.\n */\n"
            . "enum Status { case Paid; case Shipped; }"
        );

        $this->assertCount(2, $judgment->sins);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
