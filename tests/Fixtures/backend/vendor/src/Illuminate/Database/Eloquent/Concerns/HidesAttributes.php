<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HidesAttributes
{
    protected $hidden = array (
);
    protected $visible = array (
);
    /** @return array<string> */
    public function getHidden() {}
    /** @return $this */
    public function setHidden(array $hidden) {}
    /** @return $this */
    public function mergeHidden(array $hidden) {}
    /** @return array<string> */
    public function getVisible() {}
    /** @return $this */
    public function setVisible(array $visible) {}
    /** @return $this */
    public function mergeVisible(array $visible) {}
    /** @return $this */
    public function makeVisible($attributes) {}
    /** @return $this */
    public function makeVisibleIf($condition, $attributes) {}
    /** @return $this */
    public function makeHidden($attributes) {}
    /** @return $this */
    public function makeHiddenIf($condition, $attributes) {}
}
