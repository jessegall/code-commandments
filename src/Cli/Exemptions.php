<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Exemption;
use ReflectionClass;

/**
 * Lists exemption tags for a sin or detector. With no argument shows all tags; with a sin/detector
 * name shows what exemptions that detector honours, kept in sync via {@see Exemptable::exemptions}.
 */
final class Exemptions implements Command
{
    public function names(): array
    {
        return ['exemptions'];
    }

    public function help(): Help
    {
        return Help::of('List the exemption tags — what a package registers to quiet a general rule on its own boundary types.')
            ->form('exemptions', 'every registered tag, with the package that owns it')
            ->form('exemptions <sin|detector>', 'the exemptions ONE detector honours');
    }

    public function run(Input $input): int
    {
        return $input->firstArgument()->mapOrElse(
            fn () => $this->listAll(),
            fn (string $query) => $this->listFor($query),
        );
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
        $detectors = SinLookup::detectors($query);

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

            foreach (array_keys($detector->exemptions()) as $class) {
                $this->row(new $class);
            }
        }

        return 0;
    }

    private function row(Exemption $exemption): void
    {
        $this->out("  \033[36m" . str_pad($exemption->slug(), 16) . "\033[0m {$exemption->description()}\n");
    }

    private function out(string $text): void
    {
        echo $text;
    }
}
