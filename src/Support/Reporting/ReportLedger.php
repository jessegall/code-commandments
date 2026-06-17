<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Reporting;

use JesseGall\PhpTypes\T_String;

/**
 * A local record of the prophet reports THIS project has filed, so a consumer
 * can be told when one of their reports has been resolved upstream (and that
 * it's time to update the package). Persisted next to `.commandments-last-synced`.
 *
 * @phpstan-type Entry array{number: int, url: string, prophet: string, repo: string, reason: string, reported_at: string, resolved: bool, notified: bool}
 */
final class ReportLedger
{
    public const FILENAME = '.commandments-reports.json';

    public function __construct(
        private readonly string $basePath,
    ) {}

    private function path(): string
    {
        return rtrim($this->basePath, '/') . '/' . self::FILENAME;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path()), true);
        $reports = is_array($data) ? ($data['reports'] ?? null) : null;

        return is_array($reports) ? array_values($reports) : [];
    }

    /**
     * Record a freshly-filed report (no-op when the number is already tracked).
     */
    public function record(int $number, string $url, string $prophet, string $repo, string $reason, string $reportedAt): void
    {
        $reports = $this->all();

        foreach ($reports as $report) {
            if (($report['number'] ?? null) === $number && ($report['repo'] ?? null) === $repo) {
                return;
            }
        }

        $reports[] = [
            'number' => $number,
            'url' => $url,
            'prophet' => $prophet,
            'repo' => $repo,
            'reason' => $reason,
            'reported_at' => $reportedAt,
            'resolved' => false,
            'notified' => false,
        ];

        $this->write($reports);
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     */
    public function write(array $reports): void
    {
        file_put_contents(
            $this->path(),
            json_encode(['reports' => array_values($reports)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . T_String::NEWLINE,
        );
    }
}
