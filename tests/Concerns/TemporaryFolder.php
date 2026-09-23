<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Concerns;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * A fresh folder at `$this->root` for every test — made before the class's own `setUp()`, removed after
 * its `tearDown()`. The path is resolved, so a walk that resolves `/var` to `/private/var` matches it.
 */
trait TemporaryFolder
{
    private string $root;

    #[Before(1)]
    protected function makeTemporaryFolder(): void
    {
        $this->root = realpath(sys_get_temp_dir()) . '/cc-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    #[After]
    protected function removeTemporaryFolder(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }
}
