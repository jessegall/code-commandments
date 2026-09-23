<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Library;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class LibraryLanguagesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/library-languages-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        Directory::delete($this->root);
    }

    public function test_a_disabled_language_publishes_none_of_its_skills(): void
    {
        $published = new Library(Workspace::at($this->root), new Languages(Language::Python, Language::CSharp))->publish(dirname(__DIR__, 2));

        $this->assertContains('commandments-backend-absence', $published);
        $this->assertContains('commandments-frontend-vue-components', $published);
        $this->assertContains('commandments-typescript-duplication', $published);
        $this->assertSame([], array_values(array_filter($published, static fn (string $id): bool => str_starts_with($id, 'commandments-python-') || str_starts_with($id, 'commandments-csharp-'))));
    }

    public function test_switching_a_language_off_withdraws_what_it_had_published(): void
    {
        new Library(Workspace::at($this->root))->publish(dirname(__DIR__, 2));
        $library = new Library(Workspace::at($this->root), new Languages(Language::Python));
        $library->publish(dirname(__DIR__, 2));

        $this->assertDirectoryDoesNotExist($library->path('commandments-python-absence'));
        $this->assertDirectoryExists($library->path('commandments-backend-absence'));
    }
}
