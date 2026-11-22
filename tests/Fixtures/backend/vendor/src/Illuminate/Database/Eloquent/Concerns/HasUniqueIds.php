<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasUniqueIds
{
    public $usesUniqueIds = false;
    /** @return bool */
    public function usesUniqueIds() {}
    /** @return void */
    public function setUniqueIds() {}
    /** @return string */
    public function newUniqueId() {}
    /** @return array */
    public function uniqueIds() {}
}
