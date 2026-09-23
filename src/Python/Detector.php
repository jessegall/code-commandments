<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Python;

use JesseGall\CodeCommandments\Detector as BaseDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;

/**
 * A detector for Python — the same base contract as the backend's and the frontend's, over a Python
 * {@see Codebase}, returning {@see Located} matches: a {@see \JesseGall\CodeCommandments\Py\NodeMatch}
 * over a statement, or a {@see \JesseGall\CodeCommandments\Py\ExprMatch} over an expression.
 */
interface Detector extends BaseDetector
{
    /**
     * @return list<Located>
     */
    public function find(Codebase $codebase): array;
}
