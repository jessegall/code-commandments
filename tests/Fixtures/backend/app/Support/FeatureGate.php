<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Decides whether a named feature is on — the default arm answers "false" for an
 * unknown flag, masking a typo as a disabled feature.
 */
#[Sinful(InlineDocblock::class)]
#[Sinful(RedundantArrowReturnType::class)]
final class FeatureGate
{
    /** @var array<string, bool> */
    private array $overrides = [];

    public function __construct(private readonly string $environment) {}

    /**
     * No argument, so it can only be describing the gate — which is what a question is for.
     */
    #[Sinful(BareStatePredicate::class)]
    public function tracks(): bool
    {
        return $this->environment !== 'testing';
    }

    /**
     * A construction spells the class it builds; the annotation repeats it.
     */
    public function factory(): callable
    {
        return fn (): FeatureGate => new FeatureGate($this->environment);
    }

    /**
     * The FIX: the arrow's one expression already proves the type, so the `: array` comes off —
     * `fn () => $this->overrides` says everything the annotation did.
     */
    #[Fixed(RedundantArrowReturnType::class)]
    public function overridesReader(): callable
    {
        return fn () => $this->overrides;
    }

    public function override(string $flag, bool $on): void
    {
        $this->overrides[$flag] = $on;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    #[Sinful(MatchDefaultReturnsNull::class)]
    public function enabled(string $flag): bool
    {
        return match ($flag) {
            'new-checkout' => true,
            'legacy-import' => false,
            default => false,
        };
    }
}
