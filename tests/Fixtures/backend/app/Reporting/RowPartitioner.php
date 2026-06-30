<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\PositionalTupleReturn;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class RowPartitioner
{
    /**
     * @param  list<string>  $rows
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    #[Sinful(PositionalTupleReturn::class)]
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

    /**
     * The three buckets named in a typed object — callers read `->valid`, not a
     * position the order could silently rot.
     *
     * @param  list<string>  $rows
     */
    #[Righteous(PositionalTupleReturn::class)]
    public function partitionTyped(array $rows): Partitioned
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

        return new Partitioned($valid, $invalid, $errors);
    }
}
