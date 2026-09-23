<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Unpublished;

/**
 * The same mechanism assembled in ≥2 places under different names — one decision made twice.
 */
final class DuplicatedMechanism extends Sin implements Unpublished
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicated-mechanism',
            skill: FixAtTheSource::class,
            description: "Two or more classes in different files assemble the SAME rare set of collaborators — the same mechanism written twice in different words, so the decision behind it is made differently in each",
            rule: 'Before writing a mechanism, search for the concept, not the exact name you had in mind. If it already exists twice, merge the two copies into one class every caller can use, instead of adding a third.',
            suggestion: "Extract the shared mechanism into one class the callers depend on, and delete the copies.",
        );
    }
}
