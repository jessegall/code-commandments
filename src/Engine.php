<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;
use JesseGall\CodeCommandments\Testing\BackendFixture;
use JesseGall\CodeCommandments\Testing\Fixture;
use JesseGall\CodeCommandments\Testing\FrontendFixture;

/**
 * Which of the two parse engines a detector reads — the PHP AST, or the Vue components.
 * It is the ONE thing that genuinely differs between a backend and a frontend commandment, so it
 * is stated once, as a type, and everything downstream (the base interface a stub implements, the
 * codebase it queries, the fixture the guidance points at) is projected from it.
 */
enum Engine: string
{
    case Backend = 'backend';

    case Frontend = 'frontend';

    /**
     * The engine a detector belongs to. The ONE place the question is asked: a detector declares its
     * engine by the `Detector` interface it implements, and the frontend's is the only one that has
     * to be named — everything that is not it reads the PHP AST.
     */
    public static function of(Detector $detector): self
    {
        return $detector instanceof FrontendDetector ? self::Frontend : self::Backend;
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
        };
    }

    /**
     * This engine's self-checking fixture over $path, verifying $detectors against the markers it
     * finds there.
     *
     * @param  list<Detector>  $detectors
     */
    public function fixture(string $path, array $detectors): Fixture
    {
        return match ($this) {
            self::Backend => new BackendFixture($path, $detectors),
            self::Frontend => new FrontendFixture($path, $detectors),
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
        };
    }
}
