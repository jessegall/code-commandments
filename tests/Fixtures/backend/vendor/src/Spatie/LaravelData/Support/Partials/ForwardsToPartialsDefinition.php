<?php

namespace Spatie\LaravelData\Support\Partials;

trait ForwardsToPartialsDefinition
{
    abstract protected function getPartialsContainer(): object;
    public function include(string ...$includes): static {}
    public function includePermanently(string ...$includes): static {}
    public function exclude(string ...$excludes): static {}
    public function excludePermanently(string ...$excludes): static {}
    public function only(string ...$only): static {}
    public function onlyPermanently(string ...$only): static {}
    public function except(string ...$except): static {}
    public function exceptPermanently(string ...$except): static {}
    public function includeWhen(string $include, \Closure|bool $condition, bool $permanent = false): static {}
    public function excludeWhen(string $exclude, \Closure|bool $condition, bool $permanent = false): static {}
    public function onlyWhen(string $only, \Closure|bool $condition, bool $permanent = false): static {}
    public function exceptWhen(string $except, \Closure|bool $condition, bool $permanent = false): static {}
}
