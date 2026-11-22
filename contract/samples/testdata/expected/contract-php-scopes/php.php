<?php

function counter(int $step): callable
// @contract kind/block
// @contract role/block/statements -> [globalDecl, staticVar, retStmt]
{
    // @label globalDecl scope-declaration
    // @contract kind/scope-declaration
    // @contract fact/scope-declaration/form = "global"
    // @contract role/scope-declaration/bindings -> [globalName]
    // @label globalName binding
    global $total;
    // @label staticVar variable
    // @contract kind/variable
    // @contract fact/variable/form = "static"
    // @contract role/variable/declarators -> [callsDecl]
    // @label callsDecl declarator
    static $calls = 0;
    ;

    // @label retStmt return
    // @contract fact/function/form = "closure"
    // @contract role/function/captures -> [stepCapture]
    // @contract role/function/parameters -> [byParam]
    // @contract role/function/body -> closureBody
    // @label stepCapture binding 36
    // @label byBinding binding 26
    // @label byParam parameter
    // @label closureBody block
    return function (int $by) use ($step) {
        // @contract kind/binary
        // @contract fact/binary/operator = "add"
        // @contract role/binary/left -> byRef
        // @contract role/binary/right -> stepRef
        // @label byRef reference 16
        // @label stepRef reference 22
        // @contract link/reference/binding at 16 -> byBinding
        return $by + $step;
    };
}
