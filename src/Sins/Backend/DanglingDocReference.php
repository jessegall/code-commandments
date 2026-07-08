<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class DanglingDocReference extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'dangling-doc-reference',
            skill: Documentation::class,
            description: 'A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is',
            rule: 'A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can\'t be verified here.)',
        );
    }
}
