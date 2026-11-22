<?php

function scan(array $rows): int
{
    // @contract kind/literal
    // @contract role/assignment/targets -> [seenRef]
    // @contract role/assignment/values -> [zeroLit]
    // @contract fact/assignment/mode = "assign"
    // @contract fact/literal/literalKind = "integer"
    // @label seenRef reference
    // @label zeroLit literal
    $seen = 0;
    // @contract kind/reference
    // @contract role/loop/iterable -> rowsRef
    // @contract role/loop/bindings -> [rowBinding]
    // @contract role/loop/body -> rowsBody
    // @label rowsRef reference
    // @label rowBinding binding
    // @label rowsBody block
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            // @contract role/if/then -> thenBlock
            // @contract kind/comparison
            // @contract role/comparison/operands -> [cellRef, nullLit]
            // @contract link/comparison/left -> cellRef
            // @contract link/comparison/right -> nullLit
            // @contract fact/comparison/operator = "identical"
            // @contract fact/comparison/operators = ["identical"]
            // @contract fact/literal/literalKind = "null"
            // @label cellRef reference
            // @label nullLit literal
            // @label thenBlock block
            if ($cell === null) {
                // @contract kind/continue
                // @contract fact/continue/depth = 2
                continue 2;
            }
            if ($cell === '') {
                // @contract kind/break
                // @contract fact/break/depth = 2
                break 2;
            }
            // @contract fact/assignment/mode = "increment"
            // @contract fact/assignment/prefix = true
            ++$seen;
        }
    }
    // @contract kind/goto
    // @contract role/goto/destination -> doneRef
    // @contract link/goto/label -> doneLabel
    // @label doneRef reference
    goto done;
    // @contract kind/label
    // @contract role/label/binding -> doneName
    // @label doneLabel label
    // @label doneName binding
    done:
    return $seen;
}
