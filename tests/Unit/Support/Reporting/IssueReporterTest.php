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
}
