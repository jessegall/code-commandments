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

    /**
     * A CONCATENATION, not a tuple: three lists of the same element type spread into
     * one flat list, in the order the caller must apply them. No position carries a
     * meaning — element 0 is a row exactly as element 9 is — and the length is
     * whatever the operands happen to hold, so there is nothing to destructure.
     *
     * @param  list<string>  $header
     * @param  list<string>  $body
     * @param  list<string>  $footer
     *
     * @return list<string>
     */
    #[Righteous(PositionalTupleReturn::class)]
    public function concatenated(array $header, array $body, array $footer): array
    {
        return [...$header, ...$body, ...$footer];
    }
}
