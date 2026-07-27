<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use SplFileInfo;

/**
 * What counts as a PHP source file to this package — asked by every directory walk there is (the
 * codebase scan, the project's own `custom/` folder), so it is answered in one place rather than
 * re-spelled as a guard at each of them.
 */
final class PhpFile
{
    public static function is(SplFileInfo $file): bool
    {
        return $file->isFile() && $file->getExtension() === 'php';
    }
}
