<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\PhpTypes\T_String;

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
        if ($reason === null || T_String::isBlank($reason)) {
            return $this->error('A reason is required: --reason="why this rule does not apply here".');
        }

        $finding = $this->locate($fingerprint);

        if ($finding === null) {
            return $this->error(
                "No live finding matches fingerprint {$fingerprint}. It may already be fixed, "
                . 'or the code changed (which gives it a new fingerprint).'
            );
        }

        // A sin must be fixed, not absolved. The ONE escape is to `report` it as
        // genuinely wrong: that records a report-linked absolution directly, so a
        // reported sin is already suppressed and never reaches this path. Manual
        // `absolve` therefore still refuses a sin — pointing at `report` instead.
        if ($finding->isSin() && ! $this->prophet($finding->prophetClass)->requiresConfession()) {
            return $this->error(
                "{$finding->prophetShort} is a sin and must be FIXED, not absolved "
                . "({$finding->location()}). If the rule is genuinely wrong, `report` it "
                . '— that records a report-linked absolution and files an issue.'
            );
        }

        $this->tracker->absolveFinding($fingerprint, $reason);

        return [
            'status' => self::STATUS_OK,
            'message' => "Absolved {$finding->prophetShort} at {$finding->location()} — \"{$reason}\".",
        ];
    }

    /**
     * Baseline the queue: absolve every live advisory finding at once (and
     * requiresConfession sins), so `judge --next` and the warning report
     * start clean. Plain sins are NEVER absolved — they are reported back as
     * still-blocking. New findings (different fingerprints) still surface
     * later; this only accepts the CURRENT backlog.
     *
     * @return array{absolved: int, blocking_sins: int}
     */
    public function absolveAll(?string $reason): array
    {
        $reason = ($reason === null || T_String::isBlank($reason))
            ? 'Baselined: pre-existing finding accepted'
            : $reason;

        $collector = new FindingCollector($this->tracker);
        $seen = [];
        $absolved = 0;
        $blockingSins = 0;

        foreach ($this->registry->getScrolls() as $scroll) {
            $results = $this->manager->judgeScroll($scroll);

            foreach ($collector->collect($results, null, markSeen: false) as $finding) {
                if (isset($seen[$finding->fingerprint])) {
                    continue;
                }

                $seen[$finding->fingerprint] = true;

                $absolvable = $finding->isWarning()
                    || $this->prophet($finding->prophetClass)->requiresConfession();

                if (! $absolvable) {
                    $blockingSins++;

                    continue;
                }

                $this->tracker->absolveFinding($finding->fingerprint, $reason);
                $absolved++;
            }
        }

        return ['absolved' => $absolved, 'blocking_sins' => $blockingSins];
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
