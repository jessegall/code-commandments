<?php

declare(strict_types=1);

/*
 | Project the Go binary's help into the documents that describe its commands: the README's command
 | table and every `commands:` block a skill embeds. The binary is the whole tool, so its help is the
 | one the documents show. Runs under the machine's caps: two cores, 3 GiB.
 */

chdir(dirname(__DIR__));

passthru('GOMEMLIMIT=3GiB GOMAXPROCS=2 go run ./cli/doc/refresh', $status);

exit($status);
