<?php

final class Person
{
    public function __construct(public string $first = '', public string $last = '') {}

    public string $full {
        get => $this->first . ' ' . $this->last;
    }
}
