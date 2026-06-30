<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\PositionalTupleReturnDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class RowPartitioner
{
    /**
     * @param  list<string>  $rows
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    #[Sinful(PositionalTupleReturnDetector::class)]
    public function partition(array $rows): array
    {
        $valid = [];
        $invalid = [];
        $errors = [];

        foreach ($rows as $row) {
            if ($row === '') {
                $errors[] = 'empty row';
            } elseif (str_contains($row, ';')) {
                $valid[] = $row;
            } else {
                $invalid[] = $row;
            }
        }

        return [$valid, $invalid, $errors];
    }
}
