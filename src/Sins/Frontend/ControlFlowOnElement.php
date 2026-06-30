<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueControlFlow;

final class ControlFlowOnElement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'control-flow-on-element',
            skill: VueControlFlow::class,
            description: "`v-if`/`v-for`/`v-else`/`v-else-if` on an HTML/component tag instead of a `<template>`"
        );
    }
}
