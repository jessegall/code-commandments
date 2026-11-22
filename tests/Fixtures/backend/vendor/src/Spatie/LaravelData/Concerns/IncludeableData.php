<?php

namespace Spatie\LaravelData\Concerns;

trait IncludeableData
{
    use \Spatie\LaravelData\Support\Partials\ForwardsToPartialsDefinition;
    protected function getPartialsContainer(): object {}
    protected function includeProperties(): array {}
    protected function excludeProperties(): array {}
    protected function onlyProperties(): array {}
    protected function exceptProperties(): array {}
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
