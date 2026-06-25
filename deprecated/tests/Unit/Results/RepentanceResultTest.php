<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Results;

use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Tests\TestCase;

class RepentanceResultTest extends TestCase
{
    public function test_absolved_result(): void
    {
        $result = RepentanceResult::absolved(
            newContent: '<?php // fixed code',
            penance: ['Replaced raw request with typed getter'],
            blessing: '/backup/file.php.bak'
        );

        $this->assertTrue($result->absolved);
        $this->assertEquals('<?php // fixed code', $result->newContent);
        $this->assertCount(1, $result->penance);
        $this->assertEquals('/backup/file.php.bak', $result->blessing);
        $this->assertNull($result->failureReason);
    }

    public function test_unrepentant_result(): void
    {
        $result = RepentanceResult::unrepentant('Cannot auto-fix complex pattern');

        $this->assertFalse($result->absolved);
        $this->assertNull($result->newContent);
        $this->assertEquals('Cannot auto-fix complex pattern', $result->failureReason);
    }

    public function test_already_righteous_result(): void
    {
        $result = RepentanceResult::alreadyRighteous();

        $this->assertTrue($result->absolved);
        $this->assertCount(1, $result->penance);
        $this->assertStringContainsString('No sins found', $result->penance[0]);
    }
}
