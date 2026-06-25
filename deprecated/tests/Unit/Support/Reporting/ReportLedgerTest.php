<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Reporting;

use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use PHPUnit\Framework\TestCase;

class ReportLedgerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc_ledger_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/' . ReportLedger::FILENAME);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_records_and_reads_back(): void
    {
        $ledger = new ReportLedger($this->dir);
        $ledger->record(6, 'https://github.com/o/r/issues/6', 'NoRawLiteral', 'o/r', 'why', '2026-06-16T00:00:00+00:00');

        $all = (new ReportLedger($this->dir))->all();

        $this->assertCount(1, $all);
        $this->assertSame(6, $all[0]['number']);
        $this->assertSame('NoRawLiteral', $all[0]['prophet']);
        $this->assertFalse($all[0]['resolved']);
        $this->assertFalse($all[0]['notified']);
    }

    public function test_record_is_idempotent_per_number_and_repo(): void
    {
        $ledger = new ReportLedger($this->dir);
        $ledger->record(6, 'u', 'P', 'o/r', 'why', 'now');
        $ledger->record(6, 'u', 'P', 'o/r', 'why again', 'later');
        $ledger->record(6, 'u', 'P', 'other/repo', 'why', 'now');

        $this->assertCount(2, (new ReportLedger($this->dir))->all());
    }

    public function test_all_is_empty_when_no_ledger_file(): void
    {
        $this->assertSame([], (new ReportLedger($this->dir))->all());
    }
}
