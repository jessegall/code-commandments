<?php

namespace Illuminate\Foundation\Auth\Access;

trait Authorizable
{
    /** @return bool */
    public function can($abilities, $arguments = array (
)) {}
    /** @return bool */
    public function canAny($abilities, $arguments = array (
)) {}
    /** @return bool */
    public function cant($abilities, $arguments = array (
)) {}
    /** @return bool */
    public function cannot($abilities, $arguments = array (
)) {}
}
