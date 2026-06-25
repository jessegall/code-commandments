<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Reporting;

use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Tests\TestCase;

class IssueReporterTest extends TestCase
{
    public function test_builds_a_structured_issue(): void
    {
        $issue = (new IssueReporter('acme/repo'))->build(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\ExplicitDataFactoryProphet',
            'src/Foo.php',
            42,
            'False positive: PortFactory is a deliberate boundary factory.',
            'return new OutputPort(name: $field->name);',
        );

        $this->assertStringContainsString('[prophet-report] ExplicitDataFactoryProphet:', $issue['title']);
        $this->assertStringContainsString('ExplicitDataFactoryProphet', $issue['body']);
        $this->assertStringContainsString('src/Foo.php:42', $issue['body']);
        $this->assertStringContainsString('False positive', $issue['body']);
        $this->assertStringContainsString('```php', $issue['body']);
        $this->assertStringContainsString('OutputPort', $issue['body']);
        $this->assertStringContainsString('For the fixer', $issue['body']);
    }

    public function test_title_is_truncated_for_long_reasons(): void
    {
        $issue = (new IssueReporter('acme/repo'))->build(
            'FooProphet',
            null,
            null,
            str_repeat('x', 200),
            null,
        );

        // Title prefix + at most ~80 chars of reason + ellipsis.
        $this->assertLessThan(120, mb_strlen($issue['title']));
        $this->assertStringEndsWith('…', $issue['title']);
    }

    public function test_handles_missing_file_and_snippet(): void
    {
        $issue = (new IssueReporter('acme/repo'))->build('FooProphet', null, null, 'wrong rule', null);

        $this->assertStringContainsString('(no file given)', $issue['body']);
        $this->assertStringNotContainsString('```php', $issue['body']);
    }

    public function test_builds_a_feature_request_issue(): void
    {
        $issue = (new IssueReporter('acme/repo'))->buildFeatureRequest(
            'Add a prophet that flags direct model attribute writes followed by save().',
            'Flag anemic model mutations',
            'EncapsulateModelMutationProphet',
            "APPLY WHEN: a write ends in ->save().\nLEAVE WHEN: a one-off backfill.",
        );

        $this->assertSame('[feature-request] Flag anemic model mutations', $issue['title']);
        $this->assertStringContainsString('feature request (no finding attached)', $issue['body']);
        $this->assertStringContainsString('| Proposed prophet | `EncapsulateModelMutationProphet` |', $issue['body']);
        $this->assertStringContainsString('Proposed APPLY / LEAVE rubric', $issue['body']);
        $this->assertStringContainsString('APPLY WHEN', $issue['body']);
        $this->assertStringContainsString('For the maintainer', $issue['body']);
        // No false-positive scaffolding.
        $this->assertStringNotContainsString('[prophet-report]', $issue['title']);
        $this->assertStringNotContainsString('For the fixer', $issue['body']);
    }

    public function test_feature_request_title_defaults_to_reason_summary(): void
    {
        $issue = (new IssueReporter('acme/repo'))->buildFeatureRequest('Support a JSON output mode for judge.');

        $this->assertSame('[feature-request] Support a JSON output mode for judge.', $issue['title']);
        // Optional sections are omitted when not provided.
        $this->assertStringNotContainsString('Proposed prophet', $issue['body']);
        $this->assertStringNotContainsString('Proposed APPLY / LEAVE rubric', $issue['body']);
    }
}
