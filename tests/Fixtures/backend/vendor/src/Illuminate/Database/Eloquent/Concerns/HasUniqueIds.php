<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasUniqueIds
{
    public $usesUniqueIds = false;
    public function usesUniqueIds() {}
    public function setUniqueIds() {}
    public function newUniqueId() {}
    public function uniqueIds() {}
}
