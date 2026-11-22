<?php

namespace Spatie\LaravelData\Contracts;

interface ContextableData
{
    public function getDataContext(): \Spatie\LaravelData\Support\Transformation\DataContext;
}
