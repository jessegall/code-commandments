<?php

final class Person
{
    public function __construct(public string $first = '', public string $last = '') {}

    // @contract link/field/hooks -> [getHook]
    public string $full {
        // @label getHook function
        // @contract fact/function/form = "get"
        get => $this->first . ' ' . $this->last;
    }
}
