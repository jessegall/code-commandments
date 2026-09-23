<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;
use JesseGall\CodeCommandments\CSharp\Detector as CSharpDetector;
use JesseGall\CodeCommandments\Python\Detector as PythonDetector;
use JesseGall\CodeCommandments\Testing\BackendFixture;
use JesseGall\CodeCommandments\Testing\EngineFixture;
use JesseGall\CodeCommandments\Testing\FrontendFixture;
use JesseGall\CodeCommandments\Testing\ModuleFixture;

/**
 * Which parse engine a detector reads — the PHP AST, the Vue components and TypeScript modules, or
 * Python modules. It is the ONE thing that genuinely differs between one commandment and another, so it
 * is stated once, as a type, and everything downstream (the base interface a stub implements, the
 * codebase it queries, the fixture the guidance points at) is projected from it.
 */
enum Engine: string
{
    case Backend = 'backend';

    case Frontend = 'frontend';
    case Python = 'python';

    case CSharp = 'csharp';

    /**
     * The engines read module by module — a {@see ModuleCodebase} of parsed files, judged beside the
     * backend and the frontend by the same runner.
     *
     * @return list<self>
     */
    public static function modular(): array
    {
        return [self::Python, self::CSharp];
    }

    /**
     * The engine a detector belongs to. The ONE place the question is asked: a detector declares its
     * engine by the `Detector` interface it implements, and the frontend's is the only one that has
     * to be named — everything that is not it reads the PHP AST.
     */
    public static function of(Detector $detector): self
    {
        return match (true) {
            $detector instanceof FrontendDetector => self::Frontend,
            $detector instanceof PythonDetector => self::Python,
            $detector instanceof CSharpDetector => self::CSharp,
            default => self::Backend,
        };
    }

    /**
     * The engine named on the command line, or null when the word names neither.
     */
    public static function parse(string $name): ?self
    {
        return self::tryFrom(strtolower($name));
    }

    /**
     * The `Detector` interface a stub of this engine implements.
     *
     * @return class-string
     */
    public function detector(): string
    {
        return match ($this) {
            self::Backend => \JesseGall\CodeCommandments\Backend\Detector::class,
            self::Frontend => FrontendDetector::class,
            self::Python => PythonDetector::class,
            self::CSharp => CSharpDetector::class,
        };
    }

    /**
     * The codebase a `find()` of this engine is handed.
     *
     * @return class-string
     */
    public function codebase(): string
    {
        return match ($this) {
            self::Backend => \JesseGall\CodeCommandments\Ast\Codebase::class,
            self::Frontend => \JesseGall\CodeCommandments\Vue\Codebase::class,
            self::Python => \JesseGall\CodeCommandments\Py\Codebase::class,
            self::CSharp => \JesseGall\CodeCommandments\Cs\Codebase::class,
        };
    }

    /**
     * The node a `where()` closure of this engine type-hints — the whole reason the two stubs
     * differ at all.
     *
     * @return class-string
     */
    public function node(): string
    {
        return match ($this) {
            self::Backend => \JesseGall\CodeCommandments\Ast\AstNode::class,
            self::Frontend => \JesseGall\CodeCommandments\Vue\ElementMatch::class,
            self::Python => \JesseGall\CodeCommandments\Py\NodeMatch::class,
            self::CSharp => \JesseGall\CodeCommandments\Cs\NodeMatch::class,
        };
    }

    /**
     * This engine's self-checking fixture over $path, verifying $detectors against the markers it
     * finds there.
     *
     * @param  list<Detector>  $detectors
     */
    public function fixture(string $path, array $detectors): EngineFixture
    {
        return match ($this) {
            self::Backend => new BackendFixture($path, $detectors),
            self::Frontend => new FrontendFixture($path, $detectors),
            self::Python => new ModuleFixture($path, $detectors, static fn (string $root) => \JesseGall\CodeCommandments\Py\Codebase::scan($root), Language::Python),
            self::CSharp => new ModuleFixture($path, $detectors, static fn (string $root) => \JesseGall\CodeCommandments\Cs\Codebase::scan($root), Language::CSharp),
        };
    }

    /**
     * The codebase this engine reads at $path — a file or a folder — in the languages the project writes.
     *
     * @param  string|list<string>  $path
     */
    public function scan(string|array $path, Languages $languages = new Languages(), ExcludedPaths $excluded = new ExcludedPaths()): Codebase
    {
        return match ($this) {
            self::Backend => \JesseGall\CodeCommandments\Ast\Codebase::scan($path),
            self::Frontend => \JesseGall\CodeCommandments\Vue\Codebase::scan($path, languages: $languages),
            self::Python => \JesseGall\CodeCommandments\Py\Codebase::scan($path, excluded: $excluded),
            self::CSharp => \JesseGall\CodeCommandments\Cs\Codebase::scan($path, $excluded),
        };
    }

    /**
     * The engine's own default source root — where its probe file goes and what `judge` is pointed
     * at while calibrating.
     */
    public function probeRoot(): string
    {
        return match ($this) {
            self::Backend => 'src',
            self::Frontend => 'resources/js',
            self::Python => 'src',
            self::CSharp => 'src',
        };
    }

    /**
     * The probe file's extension — a `.php` class for the backend, a single-file component for the
     * frontend.
     */
    public function probeExtension(): string
    {
        return match ($this) {
            self::Backend => 'php',
            self::Frontend => 'vue',
            self::Python => 'py',
            self::CSharp => 'cs',
        };
    }
}
