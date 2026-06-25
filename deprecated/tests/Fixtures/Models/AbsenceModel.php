<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A real, loadable Eloquent model used to exercise the SEMANTIC (reflection)
 * branch of the Pattern B model exemption in
 * {@see \JesseGall\CodeCommandments\Support\Pipes\Php\FindNullObjectDefaultsCandidates}.
 */
class AbsenceModel extends Model
{
    public function paint(): void {}

    public function label(): string
    {
        return '';
    }
}
