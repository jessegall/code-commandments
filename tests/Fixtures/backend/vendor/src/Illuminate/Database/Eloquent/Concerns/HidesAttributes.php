<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HidesAttributes
{
    protected $hidden = array (
);
    protected $visible = array (
);
    public function getHidden() {}
    public function setHidden(array $hidden) {}
    public function mergeHidden(array $hidden) {}
    public function getVisible() {}
    public function setVisible(array $visible) {}
    public function mergeVisible(array $visible) {}
    public function makeVisible($attributes) {}
    public function makeVisibleIf($condition, $attributes) {}
    public function makeHidden($attributes) {}
    public function makeHiddenIf($condition, $attributes) {}
}
