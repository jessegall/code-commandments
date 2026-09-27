<?php

declare(strict_types=1);

/*
 | Regenerate every skills/commandments/<slug>/ from the catalog and the fixtures — the Go generator,
 | skill/render/generate, run in the capped dev container (scripts/dev). `--check` writes nothing and
 | fails on a stale file. A skill file is a projection: edit the skill or the sin, never the file.
 */

chdir(dirname(__DIR__));
passthru('scripts/dev go run ./skill/render/generate ' . implode(' ', array_map('escapeshellarg', array_slice($argv, 1))), $status);
exit($status);
