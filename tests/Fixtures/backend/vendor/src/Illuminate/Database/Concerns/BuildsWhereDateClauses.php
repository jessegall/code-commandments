<?php

namespace Illuminate\Database\Concerns;

trait BuildsWhereDateClauses
{
    /** @return $this */
    public function wherePast($columns) {}
    /** @return $this */
    public function whereNowOrPast($columns) {}
    /** @return $this */
    public function orWherePast($columns) {}
    /** @return $this */
    public function orWhereNowOrPast($columns) {}
    /** @return $this */
    public function whereFuture($columns) {}
    /** @return $this */
    public function whereNowOrFuture($columns) {}
    /** @return $this */
    public function orWhereFuture($columns) {}
    /** @return $this */
    public function orWhereNowOrFuture($columns) {}
    /** @return $this */
    protected function wherePastOrFuture($columns, $operator, $boolean) {}
    /** @return $this */
    public function whereToday($columns, $boolean = 'and') {}
    /** @return $this */
    public function whereBeforeToday($columns) {}
    /** @return $this */
    public function whereTodayOrBefore($columns) {}
    /** @return $this */
    public function whereAfterToday($columns) {}
    /** @return $this */
    public function whereTodayOrAfter($columns) {}
    /** @return $this */
    public function orWhereToday($columns) {}
    /** @return $this */
    public function orWhereBeforeToday($columns) {}
    /** @return $this */
    public function orWhereTodayOrBefore($columns) {}
    /** @return $this */
    public function orWhereAfterToday($columns) {}
    /** @return $this */
    public function orWhereTodayOrAfter($columns) {}
    /** @return $this */
    protected function whereTodayBeforeOrAfter($columns, $operator, $boolean) {}
}
