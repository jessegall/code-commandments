<?php

declare(strict_types=1);

/*
 | Regenerate the generated tables — README.{sins,scribes,skills}.md, their README.md excerpts, the
 | hooks and agents tables, and the journal plugin's settings — with the Go generator, cli/doc/readme,
 | run in the capped dev container (scripts/dev).
 */

chdir(dirname(__DIR__));
passthru('scripts/dev go run ./cli/doc/readme', $status);
exit($status);
