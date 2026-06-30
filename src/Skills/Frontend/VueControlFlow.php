<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Frontend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class VueControlFlow extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'frontend/vue-control-flow',
            tier: Tier::KeepInMind,
            order: 16,
        );
    }

    public function title(): string
    {
        return "Vue control flow — dispatch on a value, don't chain conditionals";
    }

    public function description(): string
    {
        return "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.";
    }

    public function intro(): string
    {
        return "A `v-if=\"status === 'paid'\"` / `v-else-if=\"status === 'pending'\"` / `v-else` chain
is a `switch` in disguise: one subject (`status`), tested case by case. Each
`v-else-if` restates the subject and reads as a fresh, independent decision when
there is really only one — *which case is this value?*";
    }

    public function summary(): string
    {
        return "dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### What is and isn't this sin

- **Is:** two or more branches that EQUALITY-test the same subject against a literal
  (`x === 'a'`, `x === 'b'`, …). The `scribe` command auto-rewrites these.
- **Is NOT** (leave it a conditional): range/predicate guards (`stock > 10`), a
  compound branch (`role === 'editor' || role === 'author'`), branches over different
  subjects, or a lone `v-if`. These aren't a single-value dispatch.

### Control flow goes on `<template>`, never on a real element

A `v-if` / `v-else-if` / `v-else` / `v-for` is STRUCTURE — which DOM renders — and it
belongs on a `<template>` wrapper, not bolted onto the element it happens to guard.
The element reads as one thing (content + styling), the `<template>` as another
(when / how-many). `repent` auto-wraps these — a `v-for` takes its `:key` along.
(`v-show` is exempt: it toggles `display` on a real node and can't live on a
`<template>`, which renders nothing.)
PRINCIPLE;
    }


    public function related(): array
    {
        return [
            VueComponents::class => "a `<SwitchCase>` IS a component — the same extract-don't-inline instinct.",
        ];
    }
}
