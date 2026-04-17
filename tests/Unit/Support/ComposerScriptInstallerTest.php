<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ComposerScriptInstaller;
use JesseGall\CodeCommandments\Tests\TestCase;

class ComposerScriptInstallerTest extends TestCase
{
    private ComposerScriptInstaller $installer;
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installer = new ComposerScriptInstaller();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'cc-cji-') . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->tempFile);
        parent::tearDown();
    }

    public function test_installs_into_empty_composer(): void
    {
        file_put_contents($this->tempFile, '{"name": "acme/foo"}');

        $status = $this->installer->install(
            $this->tempFile,
            'post-update-cmd',
            'vendor/bin/commandments sync --after=previous',
        );

        $this->assertSame(ComposerScriptInstaller::STATUS_INSTALLED, $status);

        $data = json_decode(file_get_contents($this->tempFile), true);

        $this->assertSame(
            ['vendor/bin/commandments sync --after=previous'],
            $data['scripts']['post-update-cmd'],
        );
    }

    public function test_appends_to_existing_event_list(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'name' => 'acme/foo',
            'scripts' => [
                'post-update-cmd' => ['@php -v'],
            ],
        ], JSON_PRETTY_PRINT));

        $status = $this->installer->install(
            $this->tempFile,
            'post-update-cmd',
            'vendor/bin/commandments sync --after=previous',
        );

        $this->assertSame(ComposerScriptInstaller::STATUS_INSTALLED, $status);

        $data = json_decode(file_get_contents($this->tempFile), true);

        $this->assertSame(
            ['@php -v', 'vendor/bin/commandments sync --after=previous'],
            $data['scripts']['post-update-cmd'],
        );
    }

    public function test_normalises_string_event_into_list(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'name' => 'acme/foo',
            'scripts' => [
                'post-update-cmd' => '@php -v',
            ],
        ], JSON_PRETTY_PRINT));

        $this->installer->install(
            $this->tempFile,
            'post-update-cmd',
            'vendor/bin/commandments sync --after=previous',
        );

        $data = json_decode(file_get_contents($this->tempFile), true);

        $this->assertIsArray($data['scripts']['post-update-cmd']);
        $this->assertContains('@php -v', $data['scripts']['post-update-cmd']);
        $this->assertContains(
            'vendor/bin/commandments sync --after=previous',
            $data['scripts']['post-update-cmd'],
        );
    }

    public function test_is_idempotent(): void
    {
        file_put_contents($this->tempFile, '{"name": "acme/foo"}');

        $first = $this->installer->install($this->tempFile, 'post-update-cmd', 'cmd');
        $second = $this->installer->install($this->tempFile, 'post-update-cmd', 'cmd');

        $this->assertSame(ComposerScriptInstaller::STATUS_INSTALLED, $first);
        $this->assertSame(ComposerScriptInstaller::STATUS_ALREADY_PRESENT, $second);
    }

    public function test_reports_missing_file(): void
    {
        $status = $this->installer->install('/nope/does-not-exist.json', 'post-update-cmd', 'x');

        $this->assertSame(ComposerScriptInstaller::STATUS_MISSING_FILE, $status);
    }

    public function test_reports_invalid_json(): void
    {
        file_put_contents($this->tempFile, 'not valid json');

        $status = $this->installer->install($this->tempFile, 'post-update-cmd', 'x');

        $this->assertSame(ComposerScriptInstaller::STATUS_INVALID_JSON, $status);
    }

    public function test_preserves_other_composer_keys(): void
    {
        $original = [
            'name' => 'acme/foo',
            'type' => 'project',
            'require' => ['php' => '^8.2'],
            'scripts' => [
                'test' => '@php vendor/bin/phpunit',
            ],
        ];

        file_put_contents($this->tempFile, json_encode($original, JSON_PRETTY_PRINT));

        $this->installer->install($this->tempFile, 'post-update-cmd', 'cmd');

        $data = json_decode(file_get_contents($this->tempFile), true);

        $this->assertSame('acme/foo', $data['name']);
        $this->assertSame('project', $data['type']);
        $this->assertSame(['php' => '^8.2'], $data['require']);
        $this->assertSame('@php vendor/bin/phpunit', $data['scripts']['test']);
        $this->assertSame(['cmd'], $data['scripts']['post-update-cmd']);
    }

    public function test_output_ends_with_newline_and_pretty_printed(): void
    {
        file_put_contents($this->tempFile, '{"name":"acme/foo"}');

        $this->installer->install($this->tempFile, 'post-update-cmd', 'cmd');

        $written = file_get_contents($this->tempFile);

        $this->assertStringEndsWith("\n", $written);
        // Pretty-printed → has at least one indented line
        $this->assertStringContainsString("\n    ", $written);
    }

    public function test_rejects_malformed_scripts_key(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'name' => 'acme/foo',
            'scripts' => 'this should be an object, not a string',
        ]));

        $status = $this->installer->install($this->tempFile, 'post-update-cmd', 'cmd');

        $this->assertSame(ComposerScriptInstaller::STATUS_INVALID_JSON, $status);
    }
}
