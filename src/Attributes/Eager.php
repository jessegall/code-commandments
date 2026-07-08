<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Attributes;

use Attribute;

/**
 * Opt a Spatie Data slot OUT of the constructor-orchestration lazify — pin its eager build for a value that
 * must be captured at construction time, where a `get` hook would re-resolve it against a since-changed world.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Eager {}
