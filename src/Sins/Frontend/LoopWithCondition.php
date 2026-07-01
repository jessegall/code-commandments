<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueControlFlow;

final class LoopWithCondition extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'loop-with-condition',
            skill: VueControlFlow::class,
            description: "`v-for` and `v-if`/`v-else-if` on the SAME element — the condition is re-evaluated every iteration",
            rule: "Never put `v-if` on a `v-for` element; filter in a computed, or wrap the `v-for` in a `<template>` and put the `v-if` on the child."
        );
    }
}
