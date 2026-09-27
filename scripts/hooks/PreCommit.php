<?php

/*
 | The entry an earlier installer wrote into .git/hooks/pre-commit, which every worktree of a clone shares: it hands
 | over to scripts/hooks/pre-commit, the hook itself, so a clone whose hook still names this file keeps working.
 */

$hook = __DIR__ . '/pre-commit';
passthru(escapeshellarg($hook), $status);
exit($status);
