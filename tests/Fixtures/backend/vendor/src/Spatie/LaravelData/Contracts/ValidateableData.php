<?php

namespace Spatie\LaravelData\Contracts;

interface ValidateableData
{
    public static function validate(\Illuminate\Contracts\Support\Arrayable|array $payload): \Illuminate\Contracts\Support\Arrayable|array;
    public static function validateAndCreate(\Illuminate\Contracts\Support\Arrayable|array $payload): static;
    public static function withValidator(\Illuminate\Validation\Validator $validator): void;
}
