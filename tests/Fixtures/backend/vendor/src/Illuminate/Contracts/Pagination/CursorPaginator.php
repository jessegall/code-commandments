<?php

namespace Illuminate\Contracts\Pagination;

interface CursorPaginator
{
    /** @return string */
    public function url($cursor);
    /** @return $this */
    public function appends($key, $value = NULL);
    /** @return $this|string|null */
    public function fragment($fragment = NULL);
    /** @return $this */
    public function withQueryString();
    /** @return string|null */
    public function previousPageUrl();
    /** @return string|null */
    public function nextPageUrl();
    /** @return array<TKey, */
    public function items();
    /** @return \Illuminate\Pagination\Cursor|null */
    public function previousCursor();
    /** @return \Illuminate\Pagination\Cursor|null */
    public function nextCursor();
    /** @return int */
    public function perPage();
    /** @return \Illuminate\Pagination\Cursor|null */
    public function cursor();
    /** @return bool */
    public function hasPages();
    /** @return bool */
    public function hasMorePages();
    /** @return string|null */
    public function path();
    /** @return bool */
    public function isEmpty();
    /** @return bool */
    public function isNotEmpty();
    /** @return string */
    public function render($view = NULL, $data = array (
));
}
