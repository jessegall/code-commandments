<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class BloatedDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-bloated-docblock',
            skill: Documentation::class,
            description: 'a class docstring of two or more paragraphs of prose — an essay that says the class does too much',
            rule: 'Keep a class docstring to one tight paragraph; sections for attributes and examples are fine, an essay is not.',
            suggestion: 'Cut the docstring to what the class is; if it takes an essay, split the class.',
        );
    }
}
