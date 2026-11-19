<?php

namespace Shop\Audit\Forwarding;

use JesseGall\CodeCommandments\Sins\Backend\MiddleMan;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The audit trail the checkout writes to: each entry and each flush goes straight to the journal file.
 */
#[Sinful(MiddleMan::class)]
final class AuditTrail
{
    private JournalFile $journal;

    public function __construct(JournalFile $journal)
    {
        $this->journal = $journal;
    }

    public function record(string $entry): void
    {
        $this->journal->record($entry);
    }

    public function flush(): void
    {
        $this->journal->flush();
    }

    public function pending(): int
    {
        return $this->journal->pending();
    }
}

/**
 * Lines of the audit journal, buffered until flushed to disk.
 */
final class JournalFile
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    public function __construct(private readonly string $path) {}

    public function record(string $entry): void
    {
        $this->lines[] = date('c') . ' ' . $entry;
    }

    public function pending(): int
    {
        return count($this->lines);
    }

    public function flush(): void
    {
        file_put_contents($this->path, implode("\n", $this->lines), FILE_APPEND);
        $this->lines = [];
    }
}
