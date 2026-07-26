<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class RestatedComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'restated-comment',
            skill: Documentation::class,
            description: 'An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`)',
            rule: "An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.",
        );
    }
}
