<?php

namespace Spatie\LaravelData\Concerns;

trait ContextableData
{
    protected ?\Spatie\LaravelData\Support\Transformation\DataContext $_dataContext = NULL;
    public function getDataContext(): \Spatie\LaravelData\Support\Transformation\DataContext {}
    public function setDataContext(?\Spatie\LaravelData\Support\Transformation\DataContext $dataContext): static {}
}
