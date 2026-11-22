<?php

namespace Spatie\LaravelData;

class DataPipeline
{
    protected array $normalizers = array (
);
    protected array $pipes = array (
);
    protected mixed $value;
    protected string $classString;
    protected \Spatie\LaravelData\Support\DataConfig $dataConfig;
    public function __construct(\Spatie\LaravelData\Support\DataConfig $dataConfig) {}
    public static function create(): static {}
    public function into(string $classString): static {}
    public function normalizer(\Spatie\LaravelData\Normalizers\Normalizer|string $normalizer): static {}
    public function through(\Spatie\LaravelData\DataPipes\DataPipe|string $pipe): static {}
    public function firstThrough(\Spatie\LaravelData\DataPipes\DataPipe|string $pipe): static {}
    public function endThrough(\Spatie\LaravelData\DataPipes\DataPipe|string $pipe): static {}
    public function replace(\Spatie\LaravelData\DataPipes\DataPipe|string $pipe, \Spatie\LaravelData\DataPipes\DataPipe|string $replacement): static {}
    public function resolve(): \Spatie\LaravelData\Support\ResolvedDataPipeline {}
}
