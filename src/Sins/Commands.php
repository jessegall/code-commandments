<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\RequiresBestDesign;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * The commands that ACT on a sin — find it, explain it, fix it, scaffold its helper, report it wrong.
 * Stated once, here, because the same one-liners are advertised in three places that must agree: the
 * console report, the checklist the agent prunes, and the generated skill that teaches the fix.
 */
final class Commands
{
    /**
     * How a consumer invokes us, and therefore how every documented command opens.
     */
    public const string BINARY = 'vendor/bin/commandments';

    /**
     * Explain one rule — what it flags, why it is a sin, the fix, a worked example.
     */
    public static function info(string $sin): string
    {
        return self::BINARY . " info {$sin}";
    }

    /**
     * Find every sin a skill teaches, across the codebase.
     */
    public static function judgeSkill(string $slug): string
    {
        return self::BINARY . " judge --skill={$slug}";
    }

    /**
     * Auto-fix a sin. $scope is the run to fix — `latest` targets the last judge run's checklist, so
     * the fix lands on exactly what was reported; a skill teaching the rule in the abstract has no run
     * to point at and omits it.
     */
    public static function repent(string $sin, ?string $scope = null): string
    {
        $where = $scope === null ? '' : " --repent={$scope}";

        return self::BINARY . " repent{$where} --sin={$sin}";
    }

    /**
     * Generate the generic helper a sin's fix uses.
     */
    public static function scaffold(string $sin): string
    {
        return self::BINARY . " scaffold --sin={$sin}";
    }

    /**
     * File the rule itself as wrong. The `--ref` is not optional decoration: a report without its code
     * origin cannot be reproduced, so the command that teaches it always carries one.
     */
    public static function report(string $detector, bool $bestDesign = false): string
    {
        $design = $bestDesign ? ' --best-design="…"' : '';

        return self::BINARY . " report --detector={$detector} --reason=\"…\"{$design} --ref=path:line";
    }

    /**
     * The `repent` one-liner for every auto-fixable sin, keyed by sin name — a {@see Repentable}
     * detector's. Both engines, since a Vue sin is repented by the same verb as a PHP one.
     *
     * @return array<string, string>  sin name => repent command
     */
    public static function repentable(?string $scope = null): array
    {
        $commands = [];

        foreach ([...Detectors::backend(), ...Detectors::frontend()] as $detector) {
            if ($detector instanceof Repentable) {
                $commands[$detector->sin()->name()] = self::repent($detector->sin()->name(), $scope);
            }
        }

        return $commands;
    }

    /**
     * The `scaffold` one-liner for every sin whose fix reaches for a helper we can generate.
     *
     * @return array<string, string>  sin name => scaffold command
     */
    public static function scaffoldable(): array
    {
        $commands = [];

        foreach (Catalog::every() as $sin) {
            if ($sin->scaffolds() !== []) {
                $commands[$sin->name()] = self::scaffold($sin->name());
            }
        }

        return $commands;
    }

    /**
     * Does reporting this sin's rule wrong require naming the cleanest design first? Honoured whether
     * the DETECTOR or the SIN carries the marker, exactly as the report command reads it.
     */
    public static function demandsBestDesign(Detector $detector): bool
    {
        return $detector instanceof RequiresBestDesign || $detector->sin() instanceof RequiresBestDesign;
    }

    /**
     * The detector's short class name — the `--detector=` argument a reader types.
     */
    public static function detectorName(Detector $detector): string
    {
        return ClassName::short($detector::class);
    }
}
