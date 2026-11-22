<?php

namespace Illuminate\Contracts\Pagination;

interface CursorPaginator
{
    public function url($cursor);
    public function appends($key, $value = NULL);
    public function fragment($fragment = NULL);
    public function withQueryString();
    public function previousPageUrl();
    public function nextPageUrl();
    public function items();
    public function previousCursor();
    public function nextCursor();
    public function perPage();
    public function cursor();
    public function hasPages();
    public function hasMorePages();
    public function path();
    public function isEmpty();
    public function isNotEmpty();
    public function render($view = NULL, $data = array (
));
}
