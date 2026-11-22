<?php

namespace Illuminate\Contracts\Pagination;

interface Paginator
{
    /** @return string */
    public function url($page);
    /** @return $this */
    public function appends($key, $value = NULL);
    /** @return $this|string|null */
    public function fragment($fragment = NULL);
    /** @return $this */
    public function withQueryString();
    /** @return string|null */
    public function nextPageUrl();
    /** @return string|null */
    public function previousPageUrl();
    /** @return array<TKey, */
    public function items();
    /** @return int|null */
    public function firstItem();
    /** @return int|null */
    public function lastItem();
    /** @return int */
    public function perPage();
    /** @return int */
    public function currentPage();
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
