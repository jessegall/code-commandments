<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\CodeCommandmentsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CodeCommandmentsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('commandments.scrolls', [
            'backend' => [
                'path' => __DIR__ . '/Fixtures/Backend',
                'extensions' => ['php'],
                'prophets' => [],
            ],
            'frontend' => [
                'path' => __DIR__ . '/Fixtures/Frontend',
                'extensions' => ['vue', 'ts', 'js'],
                'prophets' => [],
            ],
        ]);
    }

    protected function getFixturePath(string $type, string $status, string $filename): string
    {
        return __DIR__ . "/Fixtures/{$type}/{$status}/{$filename}";
    }

    protected function getFixtureContent(string $type, string $status, string $filename): string
    {
        $path = $this->getFixturePath($type, $status, $filename);

        return file_get_contents($path);
    }
}
