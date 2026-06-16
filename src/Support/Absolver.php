<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Results\Finding;

/**
 * Records a finding-level absolution after validating it.
 *
 * Absolution is only legitimate for advisory findings (warnings) and for
 * sins whose prophet explicitly `requiresConfession`. To enforce that — and
 * to refuse fingerprints that no longer correspond to a live finding — the
 * absolver re-locates the finding by re-scanning, rather than trusting a
 * fingerprint blindly.
 */
final class Absolver
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    /** @var array<string, Commandment> */
    private array $prophets = [];

    public function __construct(
        private readonly ScrollManager $manager,
        private readonly ProphetRegistry $registry,
        private readonly ConfessionTracker $tracker,
    ) {}

    /**
     * @return array{status: string, message: string}
     */
    public function absolve(string $fingerprint, ?string $reason): array
    {
        if ($reason === null || trim($reason) === '') {
            return $this->error('A reason is required: --reason="why this rule does not apply here".');
        }

        $finding = $this->locate($fingerprint);

        if ($finding === null) {
            return $this->error(
                "No live finding matches fingerprint {$fingerprint}. It may already be fixed, "
                . 'or the code changed (which gives it a new fingerprint).'
            );
        }

        if ($finding->isSin() && ! $this->prophet($finding->prophetClass)->requiresConfession()) {
            return $this->error(
                "{$finding->prophetShort} is a sin and must be FIXED, not absolved "
                . "({$finding->location()})."
            );
        }

        $this->tracker->absolveFinding($fingerprint, $reason);

        return [
            'status' => self::STATUS_OK,
            'message' => "Absolved {$finding->prophetShort} at {$finding->location()} — \"{$reason}\".",
        ];
    }

    private function locate(string $fingerprint): ?Finding
    {
        $collector = new FindingCollector($this->tracker);

        foreach ($this->registry->getScrolls() as $scroll) {
            $results = $this->manager->judgeScroll($scroll);

            foreach ($collector->collect($results, null, markSeen: false) as $finding) {
                if ($finding->fingerprint === $fingerprint) {
                    return $finding;
                }
            }
        }

        return null;
    }

    private function prophet(string $prophetClass): Commandment
    {
        return $this->prophets[$prophetClass] ??= new $prophetClass();
    }

    /**
     * @return array{status: string, message: string}
     */
    private function error(string $message): array
    {
        return ['status' => self::STATUS_ERROR, 'message' => $message];
    }
}
