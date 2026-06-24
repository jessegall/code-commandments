<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Call site: gates a checkout path, hand-rolling the "unknown" guard. */
final class CheckoutController
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function show(int $userId, int $teamId): string
    {
        $enabled = $this->resolver->resolve($userId, $teamId, 'express-checkout');

        // Nothing FORCED this null check — it's easy to forget and just write
        // `if ($enabled)`, which would treat the misconfiguration as "off".
        if ($enabled === null) {
            throw new UnknownFeatureFlag('express-checkout');
        }

        return $enabled ? 'express' : 'standard';
    }
}

/** Call site: re-coalesces the same nullable AGAIN with its own default. */
final class BannerRenderer
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function copy(int $userId, int $teamId): string
    {
        $on = $this->resolver->resolve($userId, $teamId, 'holiday-banner');

        // The producer already coalesced three layers; the caller coalesces a
        // FOURTH time. And `?? false` quietly buries a real `null` as `false`,
        // so a missing flag and an off flag render the same copy by accident.
        return ($on ?? false) ? 'Holiday sale is live' : 'Shop our everyday range';
    }
}

/** Call site: a third caller, a third ad-hoc `?? default` repeated by hand. */
final class ReportScheduler
{
    public function __construct(private readonly FeatureFlagResolver $resolver) {}

    public function shouldEmail(int $userId, int $teamId): bool
    {
        // Every caller re-invents absence handling, and each is one typo
        // (`?? true` vs `?? false`) away from a silent behavioural bug.
        return $this->resolver->resolve($userId, $teamId, 'weekly-email') ?? false;
    }
}
