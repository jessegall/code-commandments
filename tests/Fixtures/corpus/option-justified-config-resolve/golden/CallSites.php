<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Call site: gates a checkout path on the resolved flag, treating "unknown" as a hard error. */
final class CheckoutController
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function show(int $userId, int $teamId): string
    {
        // Absence is a domain event here: a flag with no opinion at ANY layer
        // is a misconfiguration we want to blow up on, not silently default.
        $enabled = $this->resolver->resolve($userId, $teamId, 'express-checkout')
            ->getOrThrow(fn () => new UnknownFeatureFlag('express-checkout'));

        return $enabled ? 'express' : 'standard';
    }
}

/** Call site: threads the flag through a transform without an intermediate null check. */
final class BannerRenderer
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function copy(int $userId, int $teamId): string
    {
        // map() runs only when present; getOrElse supplies the rendering fallback.
        // No `?->` / `?? null ?? ...` ladder — the chain stays flat.
        return $this->resolver->resolve($userId, $teamId, 'holiday-banner')
            ->map(fn (bool $on) => $on ? 'Holiday sale is live' : 'Shop our everyday range')
            ->getOrElse('Shop our everyday range');
    }
}

/** Call site: a reporting toggle that genuinely wants the system default when absent. */
final class ReportScheduler
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function shouldEmail(int $userId, int $teamId): bool
    {
        // A third caller, with a THIRD absence policy (default false). Each
        // caller decides its own handling — the producer doesn't pick for them.
        return $this->resolver->resolve($userId, $teamId, 'weekly-email')
            ->getOrElse(false);
    }
}
