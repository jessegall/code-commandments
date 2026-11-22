<?php

namespace Illuminate\Database\Concerns;

trait BuildsWhereDateClauses
{
    public function wherePast($columns) {}
    public function whereNowOrPast($columns) {}
    public function orWherePast($columns) {}
    public function orWhereNowOrPast($columns) {}
    public function whereFuture($columns) {}
    public function whereNowOrFuture($columns) {}
    public function orWhereFuture($columns) {}
    public function orWhereNowOrFuture($columns) {}
    protected function wherePastOrFuture($columns, $operator, $boolean) {}
    public function whereToday($columns, $boolean = 'and') {}
    public function whereBeforeToday($columns) {}
    public function whereTodayOrBefore($columns) {}
    public function whereAfterToday($columns) {}
    public function whereTodayOrAfter($columns) {}
    public function orWhereToday($columns) {}
    public function orWhereBeforeToday($columns) {}
    public function orWhereTodayOrBefore($columns) {}
    public function orWhereAfterToday($columns) {}
    public function orWhereTodayOrAfter($columns) {}
    protected function whereTodayBeforeOrAfter($columns, $operator, $boolean) {}
}
