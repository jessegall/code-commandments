<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\PlanProfile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The constraint state for the active plan — the natural-language invariants the run must hold to (the
 * global ones from {@see PlanProfile::constraints} plus this run's local additions), and whether the
 * agent has verified them against its branch diff. Verification is a HEAD stamp, fresh only while HEAD
 * is unchanged. Session-scoped like {@see PlanMarker}.
 */
final class PlanConstraints
{
    public function __construct(
        private readonly string $localPath,
        private readonly string $verifiedPath,
        private readonly PlanProfile $plan,
    ) {}

    public static function inSession(Workspace $workspace, PlanProfile $plan): self
    {
        return new self(
            $workspace->path('.plan-constraints'),
            $workspace->path('.constraints-verified'),
            $plan,
        );
    }

    /**
     * Every constraint in force for this run — the global ones first, then this run's local additions.
     *
     * @return list<string>
     */
    public function active(): array
    {
        return [...$this->plan->constraints(), ...$this->local()];
    }

    /**
     * The constraints added for THIS run only.
     *
     * @return list<string>
     */
    public function local(): array
    {
        return $this->lines($this->localPath);
    }

    /**
     * Record a constraint for this run — idempotent, so re-adding the same rule is a no-op.
     */
    public function addLocal(string $rule): void
    {
        $rule = trim($rule);
        $existing = $this->local();

        if ($rule === '' || in_array($rule, $existing, true)) {
            return;
        }

        $this->write($this->localPath, [...$existing, $rule]);
    }

    /**
     * Stamp that the constraints were verified at $head.
     */
    public function markVerified(string $head): void
    {
        $this->write($this->verifiedPath, [$head]);
    }

    /**
     * Were the constraints verified at exactly $head — the current HEAD, with no commit since? A moved
     * HEAD is stale (new work to re-check); an empty $head (no commits yet) is never fresh.
     */
    public function isVerifiedAt(string $head): bool
    {
        return $head !== '' && ($this->lines($this->verifiedPath)[0] ?? null) === $head;
    }

    /**
     * Drop this run's local constraints AND its verification stamp — the plan is finished.
     */
    public function clear(): void
    {
        @unlink($this->localPath);
        @unlink($this->verifiedPath);
    }

    /**
     * @return list<string>  the non-blank lines of $path, trimmed; [] when the file is absent.
     */
    private function lines(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) file_get_contents($path)) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @param  list<string>  $lines
     */
    private function write(string $path, array $lines): void
    {
        @mkdir(dirname($path), 0777, true);
        @file_put_contents($path, implode("\n", $lines) . "\n");
    }
}
