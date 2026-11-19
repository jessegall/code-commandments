<?php

namespace Shop\Ledger;

use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The shop's ledger: entries are posted and the balance is their sum.
 */
class LedgerBook
{
    /**
     * @var list<int>
     */
    protected array $entries = [];

    public function post(int $cents): void
    {
        $this->entries[] = $cents;
    }

    public function balance(): int
    {
        return array_sum($this->entries);
    }
}

/**
 * A ledger for reports that must never change it, made by extending the ledger and refusing to post.
 */
final class ReportLedger extends LedgerBook
{
    public function lines(): string
    {
        $lines = '';
        foreach ($this->entries as $number => $cents) {
            $lines .= sprintf("%03d %10.2f
", $number, $cents / 100);
        }

        return $lines;
    }

    #[Sinful(RefusedBequest::class)]
    public function post(int $cents): void
    {
        throw LedgerIsReadOnly::forPosting($cents);
    }
}

/**
 * The report's view of a ledger: it holds one and offers only what a report may do, so it has nothing to refuse.
 */
#[Fixed(RefusedBequest::class)]
final class LedgerReport
{
    public function __construct(private readonly LedgerBook $ledger) {}

    public function balance(): int
    {
        return $this->ledger->balance();
    }
}

/**
 * Posting to a ledger that only reports.
 */
final class LedgerIsReadOnly extends \RuntimeException
{
    public static function forPosting(int $cents): self
    {
        return new self("A report ledger cannot post {$cents} cents.");
    }
}
