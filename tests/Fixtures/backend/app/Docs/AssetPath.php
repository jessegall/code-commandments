<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Names a doc asset by where it sits under the docs root. The root is resolved first, because the
 * configured path reaches the app through a symlink on every deploy and a raw prefix test would say
 * nothing under it belongs to it.
 */
final class AssetPath
{
    #[Sinful(DivergentTwin::class)]
    public function relativeTo(string $file, string $root): string
    {
        $prefix = rtrim(realpath($root) ?: $root, '/') . '/';

        if (! str_starts_with($file, $prefix)) {
            return basename($file);
        }

        return substr($file, strlen($prefix));
    }
}
