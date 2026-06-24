<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `absolve` — clear, clear-until-push, batch --warnings,
 * --all baseline, and single fingerprint/--at absolution. One implementation both
 * command variants call; they only differ in how they obtain the
 * manager/registry/tracker and where they print.
 */
final class AbsolveService
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    /**
     * @param  array<string, mixed>  $opts  clear, clear_until_push, until_push, warnings, scope, prophet, all, fingerprint, at, reason
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public static function run(
        ScrollManager $manager,
        ProphetRegistry $registry,
        ConfessionTracker $tracker,
        array $opts,
        string $basePath,
        callable $emit,
        callable $error,
    ): int {
        if ((bool) ($opts['clear_until_push'] ?? false)) {
            $emit('Cleared ' . $tracker->clearUntilPushAbsolutions() . ' push-scoped absolution(s).');

            return self::SUCCESS;
        }

        if ((bool) ($opts['clear'] ?? false)) {
            $emit('Cleared ' . $tracker->clearFindingAbsolutions() . ' absolution(s). Every finding will be re-evaluated from scratch.');

            return self::SUCCESS;
        }

        $untilPush = (bool) ($opts['until_push'] ?? false);
        $reason = $opts['reason'] ?? null;
        $prophet = is_string($opts['prophet'] ?? null) && $opts['prophet'] !== '' ? $opts['prophet'] : null;

        if ((bool) ($opts['warnings'] ?? false)) {
            $scopeFiles = self::resolveScope($opts['scope'] ?? null, $basePath, $error);

            if ($scopeFiles === false) {
                return self::FAILURE;
            }

            $result = (new Absolver($manager, $registry, $tracker))
                ->absolveWarnings($reason, $scopeFiles, $untilPush, $prophet);

            $result['status'] === Absolver::STATUS_OK ? $emit($result['message']) : $error($result['message']);

            return $result['status'] === Absolver::STATUS_OK ? self::SUCCESS : self::FAILURE;
        }

        if ((bool) ($opts['all'] ?? false)) {
            $result = (new Absolver($manager, $registry, $tracker))->absolveAll($reason);
            $emit("Baselined the queue: absolved {$result['absolved']} advisory finding(s).");

            if ($result['blocking_sins'] > 0) {
                $r = ClaudeHooksInstaller::runnerFor($basePath);
                $error("{$result['blocking_sins']} sin(s) cannot be absolved and still block — fix them: walk with {$r[0]}{$r[1]}pilgrimage then {$r[0]}{$r[1]}next");
            }

            return self::SUCCESS;
        }

        $absolver = new Absolver($manager, $registry, $tracker);
        $fingerprint = $opts['fingerprint'] ?? null;
        $at = $opts['at'] ?? null;

        if ((! is_string($fingerprint) || T_String::isBlank($fingerprint)) && is_string($at) && T_String::isNotBlank($at)) {
            $fingerprint = self::resolveAt($absolver, $at, $prophet, $error);

            if ($fingerprint === null) {
                return self::FAILURE;
            }
        }

        if (! is_string($fingerprint) || T_String::isBlank($fingerprint)) {
            $error('Pass --fingerprint=<hash> or --at=path:line (the file:line shown under each pilgrimage / next step).');

            return self::FAILURE;
        }

        $result = $absolver->absolve(trim($fingerprint), $reason, $untilPush);

        if ($result['status'] === Absolver::STATUS_OK) {
            $emit($result['message']);

            return self::SUCCESS;
        }

        $error($result['message']);

        return self::FAILURE;
    }

    /**
     * @return list<string>|null|false  files to scope to, null for all, false on a bad --scope (error already emitted)
     * @param  callable(string): void  $error
     */
    private static function resolveScope(mixed $scope, string $basePath, callable $error): array|null|false
    {
        $files = match ($scope) {
            null => null,
            'git' => GitFileDetector::for($basePath)->getChangedFiles(),
            'staged' => GitFileDetector::for($basePath)->getStagedFiles(),
            default => false,
        };

        if ($files === false) {
            $error('--scope must be "git" or "staged".');
        }

        return $files;
    }

    /**
     * @param  callable(string): void  $error
     */
    private static function resolveAt(Absolver $absolver, string $at, ?string $prophet, callable $error): ?string
    {
        $loc = Absolver::parseLocator($at);

        if ($loc === null) {
            $error('--at must be path:line or path:from-to (e.g. --at=src/Foo.php:32).');

            return null;
        }

        $unique = [];

        foreach ($absolver->findingsAt($loc['path'], $loc['from'], $loc['to'], $prophet) as $finding) {
            $unique[$finding->fingerprint] = $finding;
        }

        if ($unique === []) {
            $error("No live finding at {$at}" . ($prophet !== null ? " for a prophet matching '{$prophet}'" : T_String::empty()) . '. Run next (the pilgrimage step) or judge to see current findings.');

            return null;
        }

        if (count($unique) > 1) {
            $error("Multiple findings at {$at} — narrow with --prophet=NAME:");

            foreach ($unique as $finding) {
                $error("  - {$finding->prophetShort} ({$finding->location()})");
            }

            return null;
        }

        return array_values($unique)[0]->fingerprint;
    }
}
