<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class ArchaeologyComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-archaeology-comment',
            skill: Documentation::class,
            description: 'a comment that tells the code\'s past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading',
            rule: 'Say what the code is now, never what it was; git keeps the history.',
            suggestion: 'Delete the history. If something about the present needs saying, say that instead.',
        );
    }
}
