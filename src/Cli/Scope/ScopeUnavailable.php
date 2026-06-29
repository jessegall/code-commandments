<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use RuntimeException;

/**
 * A `--git`/`--branch` scope could not be resolved — the path isn't in a git
 * repository, git is unavailable, or the base ref doesn't exist. Its message is
 * printed to STDERR and the command exits non-zero.
 */
final class ScopeUnavailable extends RuntimeException {}
