<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * Runs an item's check and files what came back. This is the whole difference between a receipt and a
 * report: the agent names the command, the tool runs it, and the number in the record is the one a
 * process actually returned. An agent that wanted to report a different number would have to change what
 * the command prints.
 */
final readonly class Verification
{
    public function __construct(
        private string $root,
        private GitFiles $git = new GitFiles,
    ) {}

    /**
     * Run $argv for $item and file what it said, stamped with the tree it stood in. $needs is a
     * precondition — where it fails, the check is not run at all and the receipt says it could not
     * measure, rather than reporting the environment's failure as the work's.
     */
    public function of(string $item, string $argv, string $base, ?string $needs = null): Receipt
    {
        $unmeasurable = $this->whatStopsIt($needs);
        $output = [];
        $exitCode = 0;

        if ($unmeasurable === null) {
            exec('cd ' . escapeshellarg($this->root) . ' && ' . $argv . ' 2>&1', $output, $exitCode);
        }

        return new Receipt(
            $item,
            $argv,
            $exitCode,
            substr($this->git->head($this->root), 0, 7), // Abbreviated, as the merge-base is — a sha is read by eye.
            $this->mergeBaseWith($base),
            gmdate('H:i'),
            implode("\n", array_slice($output, -20)),
            $unmeasurable,
        );
    }

    /**
     * What would stop this check from measuring anything — the precondition failing. A gate that needs a
     * running rig fails for the rig rather than for the work, and filing THAT exit code as a verdict is a
     * receipt that lies with provenance, which is worse than no receipt.
     */
    private function whatStopsIt(?string $needs): ?string
    {
        if ($needs === null) {
            return null;
        }

        $output = [];
        $exitCode = 0;

        exec('cd ' . escapeshellarg($this->root) . ' && ' . $needs . ' 2>&1', $output, $exitCode);

        return $exitCode === 0 ? null : "`{$needs}` did not hold (exit {$exitCode}), so nothing was measured";
    }

    /**
     * Where this tree last agreed with $base — the fact that separates a lane's honest number from the
     * branch's. Absent as a dash rather than an empty string, so a reader can tell "not asked" from a
     * base that could not be resolved.
     */
    private function mergeBaseWith(string $base): string
    {
        if ($base === '') {
            return '-';
        }

        $sha = trim((string) @shell_exec(
            'git -C ' . escapeshellarg($this->root) . ' merge-base ' . escapeshellarg($base) . ' HEAD 2>/dev/null',
        ));

        return $sha === '' ? '-' : substr($sha, 0, 7);
    }
}
