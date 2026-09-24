<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class CeremonyDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-ceremony-docblock',
            skill: Documentation::class,
            description: 'a doc comment whose every tag is empty or only repeats the signature — `<param name="order">The order.</param>`, an empty `<returns>`',
            rule: 'A doc comment must say something the signature does not; drop tags that only repeat a name or a type.',
            suggestion: 'Delete the comment, or write the sentence that says what the member does and describe only what a name and a type cannot.',
        );
    }
}
