<?php

namespace Shop\Reporting;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ModelMutationAtCallSite;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Moves a timestamp column of any table onto UTC, for a maintenance command that walks every table it is given.
 */
final class StampedAtFixer
{
    #[Righteous(ModelMutationAtCallSite::class)]
    public function fix(Model $row, string $column): void
    {
        $row->{$column} = $row->getAttribute($column)?->setTimezone('UTC');
        $row->save();
    }
}
