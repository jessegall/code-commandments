<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\CommandmentsUpdater;
use PHPUnit\Framework\TestCase;

class CommandmentsUpdaterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-updater-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function test_wires_update_into_both_composer_lifecycle_events(): void
    {
        $this->writeComposer(['name' => 'acme/app']);

        CommandmentsUpdater::ensureComposerScripts($this->dir, static fn () => null, static fn () => null);

        $scripts = $this->readComposer()['scripts'];
        $this->assertContains(CommandmentsUpdater::COMPOSER_COMMAND, $scripts['post-update-cmd']);
        $this->assertContains(CommandmentsUpdater::COMPOSER_COMMAND, $scripts['post-install-cmd']);
    }

    public function test_is_append_only_and_idempotent(): void
    {
        $this->writeComposer(['scripts' => ['post-update-cmd' => ['@php artisan optimize']]]);

        CommandmentsUpdater::ensureComposerScripts($this->dir, static fn () => null, static fn () => null);
        CommandmentsUpdater::ensureComposerScripts($this->dir, static fn () => null, static fn () => null);

        $postUpdate = $this->readComposer()['scripts']['post-update-cmd'];
        $this->assertSame(['@php artisan optimize', CommandmentsUpdater::COMPOSER_COMMAND], $postUpdate);
        $this->assertSame(1, array_count_values($postUpdate)[CommandmentsUpdater::COMPOSER_COMMAND]);
    }

    public function test_a_string_form_script_is_preserved(): void
    {
        $this->writeComposer(['scripts' => ['post-install-cmd' => '@php artisan key:generate']]);

        CommandmentsUpdater::ensureComposerScripts($this->dir, static fn () => null, static fn () => null);

        $postInstall = $this->readComposer()['scripts']['post-install-cmd'];
        $this->assertSame(['@php artisan key:generate', CommandmentsUpdater::COMPOSER_COMMAND], $postInstall);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeComposer(array $data): void
    {
        file_put_contents($this->dir . '/composer.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposer(): array
    {
        return json_decode((string) file_get_contents($this->dir . '/composer.json'), true);
    }
}
