<?php

namespace Spatie\LaravelData;

abstract class Data implements \Illuminate\Contracts\Support\Responsable, \Spatie\LaravelData\Contracts\AppendableData, \Spatie\LaravelData\Contracts\BaseData, \Spatie\LaravelData\Contracts\TransformableData, \Spatie\LaravelData\Contracts\ContextableData, \Spatie\LaravelData\Contracts\IncludeableData, \Spatie\LaravelData\Contracts\ResponsableData, \Spatie\LaravelData\Contracts\ValidateableData, \Spatie\LaravelData\Contracts\WrappableData, \Spatie\LaravelData\Contracts\EmptyData, \Illuminate\Contracts\Database\Eloquent\Castable, \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable
{
    use \Spatie\LaravelData\Concerns\ResponsableData, \Spatie\LaravelData\Concerns\IncludeableData, \Spatie\LaravelData\Concerns\AppendableData, \Spatie\LaravelData\Concerns\ValidateableData, \Spatie\LaravelData\Concerns\WrappableData, \Spatie\LaravelData\Concerns\TransformableData, \Spatie\LaravelData\Concerns\BaseData, \Spatie\LaravelData\Concerns\EmptyData, \Spatie\LaravelData\Concerns\ContextableData;
}
