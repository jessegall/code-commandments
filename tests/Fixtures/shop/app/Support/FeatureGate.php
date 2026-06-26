<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Decides whether a named feature is on — the default arm answers "false" for an
 * unknown flag, masking a typo as a disabled feature.
 */
final class FeatureGate
{
    /** @var array<string, bool> */
    private array $overrides = [];

    public function __construct(private readonly string $environment) {}

    public function override(string $flag, bool $on): void
    {
        $this->overrides[$flag] = $on;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    #[Sinful(MatchDefaultReturnsNullDetector::class)]
    public function enabled(string $flag): bool
    {
        return match ($flag) {
            'new-checkout' => true,
            'legacy-import' => false,
            default => false,
        };
    }
}
