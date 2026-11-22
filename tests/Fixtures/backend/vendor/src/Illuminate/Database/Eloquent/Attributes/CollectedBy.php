<?php

namespace Illuminate\Database\Eloquent\Attributes;

#[\Attribute(1)]
class CollectedBy
{
    public string $collectionClass;
    public function __construct(string $collectionClass) {}
}
