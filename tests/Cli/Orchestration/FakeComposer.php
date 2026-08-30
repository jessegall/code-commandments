<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Composer;
use JesseGall\CodeCommandments\Cli\Orchestration\Release;

/**
 * A {@see Composer} that runs no process — what composer would say over the network is exactly the fact a
 * test has no business measuring, and a test that arranged an answer for it would have decided the thing
 * under test. It records which steps were asked for, in order, which is what the "each step only when the
 * one before worked" rule is actually about.
 */
final class FakeComposer extends Composer
{
    /** @var list<string> every step asked of it, in order. */
    public array $ran = [];

    /** @var list<string> the checkout each step was pointed at — a lane getting one is the bug. */
    public array $roots = [];

    /**
     * @param  ?string  $installs  the version the update writes into the project's lockfile — absent when
     *                             the update fails, since a failed update installs nothing.
     */
    public function __construct(
        private readonly Release $release,
        private readonly int $updates = 0,
        private readonly int $syncs = 0,
        private readonly ?string $installs = null,
    ) {}

    public function latestFor(string $root): Release
    {
        $this->ran[] = 'latest';

        return $this->release;
    }

    public function update(string $root): int
    {
        $this->ran[] = 'update';
        $this->roots[] = $root;

        if ($this->installs !== null) {
            Lockfile::write($root, $this->installs);
        }

        return $this->updates;
    }

    public function sync(string $root): int
    {
        $this->ran[] = 'sync';

        return $this->syncs;
    }
}
