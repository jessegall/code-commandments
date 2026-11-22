<?php

namespace Illuminate\Foundation\Auth\Access;

trait Authorizable
{
    public function can($abilities, $arguments = array (
)) {}
    public function canAny($abilities, $arguments = array (
)) {}
    public function cant($abilities, $arguments = array (
)) {}
    public function cannot($abilities, $arguments = array (
)) {}
}
