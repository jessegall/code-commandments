<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Concerns;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

/**
 * A sin with nothing to teach, for a test that needs a detector to name one.
 */
final class Probe extends Sin
{
    public function __construct()
    {
        parent::__construct(name: 'probe', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
    }
}
