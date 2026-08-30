<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\Binary;

/**
 * The processes that MOVE this package in a project — what composer says is installable, the update
 * itself, and the sync that republishes what the new one teaches; one collaborator because they are one
 * act, which is why a consumer's `post-update-cmd` already runs `sync`. Not final: it is the seam a test
 * replaces, since what composer would say over the network is the one fact a test has no business
 * measuring.
 */
class Composer
{
    /**
     * The version $root could move to, asked of composer in $root so the project's OWN constraint and
     * repositories decide the answer — the newest tag on packagist is a different sentence from the one
     * this project can install.
     *
     * It is a measurement or it is nothing: composer may not be on the PATH, the network may be down, the
     * repository may be unreachable. Every one of those answers {@see Release::COULD_NOT_MEASURE} with
     * the reason, and never a number.
     */
    public function latestFor(string $root): Release
    {
        $command = 'composer show --latest --format=json ' . escapeshellarg(Checkout::PACKAGE);
        $output = [];
        $exitCode = 0;

        exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0) {
            return Release::unmeasurable("`{$command}` exited {$exitCode} — composer could not say what is available.");
        }

        $shown = json_decode(self::json($output), true);
        $latest = is_array($shown) ? ($shown['latest'] ?? null) : null;

        return is_string($latest) && $latest !== ''
            ? Release::measured($latest)
            : Release::unmeasurable("`{$command}` answered without a `latest` — nothing to read.");
    }

    /**
     * Move the package. The output is passed straight through: an update is the slow, failable half, and
     * a person watching it wants composer's own words rather than ours about them.
     */
    public function update(string $root): int
    {
        $ran = 0;

        passthru(
            'cd ' . escapeshellarg($root)
            . ' && composer update ' . escapeshellarg(Checkout::PACKAGE) . ' --no-interaction',
            $ran,
        );

        return $ran;
    }

    /**
     * Republish what the package teaches, through the executable the project has NOW — a subprocess on
     * purpose, since the process running this one is the version being replaced and would publish the old
     * curriculum over the new binary.
     */
    public function sync(string $root): int
    {
        $ran = 0;

        passthru('cd ' . escapeshellarg($root) . ' && ' . Binary::in($root) . ' sync', $ran);

        return $ran;
    }

    /**
     * The JSON in composer's answer. Run where there is no `composer.json` it prefixes a line of prose
     * about that, so the object is taken from where it starts rather than assumed to be the whole reply.
     *
     * @param  list<string>  $output
     */
    private static function json(array $output): string
    {
        while ($output !== [] && ! str_starts_with(ltrim($output[0]), '{')) {
            array_shift($output);
        }

        return implode("\n", $output);
    }
}
