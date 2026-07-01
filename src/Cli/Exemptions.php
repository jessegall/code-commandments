<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Exemption;
use ReflectionClass;

/**
 * `commandments exemptions [<sin|detector>]` — inspect the exemption system.
 *
 * With no argument it lists every exemption tag (its slug + what it means). With a sin id or
 * detector name it lists the exemptions THAT detector honours — exactly what a package can register
 * (by slug or class) to quiet it. It reads each detector's own {@see Exemptable::exemptions}
 * declaration, so it can never drift from what the detector actually reads.
 */
final class Exemptions
{
    public function run(array $args): int
    {
        $query = $this->firstArgument($args);

        return $query === null ? $this->listAll() : $this->listFor($query);
    }

    private function listAll(): int
    {
        $this->out("\033[1mExemptions\033[0m — what a package registers to quiet a general rule (by slug or class).\n\n");

        foreach (Exemption::all() as $class) {
            $this->row(new $class);
        }

        $this->out("\n\033[2mRun `commandments exemptions <sin|detector>` to see one detector's exemptions.\033[0m\n");

        return 0;
    }

    private function listFor(string $query): int
    {
        $detectors = $this->detectorsFor($query);

        if ($detectors === []) {
            fwrite(STDERR, "No sin or detector matches \"{$query}\". Run `commandments judge --list` to see them.\n");

            return 2;
        }

        foreach ($detectors as $detector) {
            $name = new ReflectionClass($detector)->getShortName();

            if (! $detector instanceof Exemptable || $detector->exemptions() === []) {
                $this->out("\033[1m{$name}\033[0m honours no exemptions.\n");

                continue;
            }

            $this->out("\033[1m{$name}\033[0m honours:\n");

            foreach ($detector->exemptions() as $class) {
                $this->row(new $class);
            }
        }

        return 0;
    }

    private function row(Exemption $exemption): void
    {
        $this->out("  \033[36m" . str_pad($exemption->slug(), 16) . "\033[0m {$exemption->description()}\n");
    }

    /**
     * The detectors a query names — by its sin id (lenient) or its detector short name.
     *
     * @return list<\JesseGall\CodeCommandments\Detector>
     */
    private function detectorsFor(string $query): array
    {
        $needle = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $query));

        return array_values(array_filter(Catalog::all(), static function ($detector) use ($query, $needle): bool {
            $short = strtolower(new ReflectionClass($detector)->getShortName());

            return $detector->sin()->matches($query) || str_contains($short, $needle);
        }));
    }

    private function firstArgument(array $args): ?string
    {
        foreach ($args as $arg) {
            if (! str_starts_with($arg, '--')) {
                return $arg;
            }
        }

        return null;
    }

    private function out(string $text): void
    {
        echo $text;
    }
}
