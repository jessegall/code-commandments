<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A detector (or its {@see Sins\Sin}) whose false-positive report must be JUSTIFIED with the cleanest
 * design the reporter can conceive — `commandments report --detector=… --best-design="…"`. Reserved for
 * the design-smell rules where "a false positive" is the exact shape of a dodged fix: an all-nullable DTO,
 * a primitive that wants a value object, a conditional that wants a sum type — cases where the reporter
 * almost always CAN name something cleaner, and that cleaner thing IS the owed fix.
 *
 * A marker, not a config flag, mirroring {@see Unpublished}: the requirement lives with the class, and it
 * is honoured whether the DETECTOR or the SIN carries it (so a whole sin can opt in once). Detectors
 * without it report as before — `--best-design` stays optional. {@see Cli\Report\Report} reads it by
 * resolving `--detector=NAME` through {@see Detectors\Catalog::detectorNamed()}.
 */
interface RequiresBestDesign {}
