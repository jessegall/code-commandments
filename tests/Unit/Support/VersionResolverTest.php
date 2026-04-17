<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\VersionResolver;
use JesseGall\CodeCommandments\Tests\TestCase;

class VersionResolverTest extends TestCase
{
    private string $tempDir;
    private VersionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/cc-vr-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->resolver = new VersionResolver();
    }

    protected function tearDown(): void
    {
        @unlink($this->resolver->stateFilePath($this->tempDir));
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_returns_null_when_no_previous_sync_recorded(): void
    {
        $this->assertNull($this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_records_and_reads_back_version(): void
    {
        $this->assertTrue($this->resolver->recordSyncedVersion($this->tempDir, '1.4.2'));
        $this->assertSame('1.4.2', $this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_strips_leading_v_prefix(): void
    {
        $this->resolver->recordSyncedVersion($this->tempDir, 'v1.4.2');
        $this->assertSame('1.4.2', $this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_returns_null_for_dev_version(): void
    {
        $this->resolver->recordSyncedVersion($this->tempDir, 'dev-main');
        $this->assertNull($this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_returns_null_for_garbage_content(): void
    {
        file_put_contents($this->resolver->stateFilePath($this->tempDir), "not a version\n");
        $this->assertNull($this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_accepts_prerelease_and_build_metadata(): void
    {
        $this->resolver->recordSyncedVersion($this->tempDir, '1.5.0-beta.1');
        $this->assertSame('1.5.0-beta.1', $this->resolver->previousSyncedVersion($this->tempDir));
    }

    public function test_state_file_path_is_project_relative(): void
    {
        $path = $this->resolver->stateFilePath('/foo/bar');
        $this->assertSame('/foo/bar/.commandments-last-synced', $path);
    }

    public function test_state_file_path_strips_trailing_slash(): void
    {
        $path = $this->resolver->stateFilePath('/foo/bar/');
        $this->assertSame('/foo/bar/.commandments-last-synced', $path);
    }

    public function test_current_version_is_null_or_valid_semver(): void
    {
        $v = $this->resolver->currentVersion();

        // During package-local dev, getPrettyVersion returns dev-main-ish → null
        // is the expected outcome. If a real pinned version is installed, it
        // should be a valid semver string.
        if ($v !== null) {
            $this->assertMatchesRegularExpression(
                '/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/',
                $v,
            );
        } else {
            $this->assertNull($v);
        }
    }
}
