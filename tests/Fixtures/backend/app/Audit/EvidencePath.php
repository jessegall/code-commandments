<?php

namespace Shop\Audit;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Docs\AssetPath;

/**
 * Names an evidence file by where it sits under the evidence root — the same question the docs asset
 * answers, asked against the path as configured. The two disagree the moment either root is a symlink.
 */
final class EvidencePath
{
    public function __construct(private readonly AssetPath $assets) {}

    #[Sinful(DivergentTwin::class)]
    public function under(string $file, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';

        if (! str_starts_with($file, $prefix)) {
            return basename($file);
        }

        return substr($file, strlen($prefix));
    }

    /**
     * The same question, asked through the one place that knows a root has to be resolved first.
     */
    #[Fixed(DivergentTwin::class)]
    public function evidenceUnder(string $file, string $root): string
    {
        return $this->assets->relativeTo($file, $root);
    }
}
