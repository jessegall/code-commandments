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

    protected function setUp(): void
    {
        parent::setUp();

        // The testbench base path is shared across tests; a test that installs a
        // profile (e.g. install-hooks → `profile phased`) leaves `.commandments/profile`
        // behind, which would shift a later bare-`judge`'s scope. Reset it so every
        // test starts from the default (no explicit profile = full-scan judge).
        @unlink(base_path('.commandments/profile'));
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
