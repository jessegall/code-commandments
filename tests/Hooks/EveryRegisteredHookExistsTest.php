<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use PHPUnit\Framework\TestCase;

/**
 * A registry that names a class the package does not ship is a FATAL, and it fails early: `sync` writes
 * the harness bindings, so it dies part-way through setting a project up and leaves a half-configured
 * one behind. A rename that moves a file and leaves the import is all it takes.
 *
 * Nothing else catches it. Every other test constructs the handlers it already knows about, so the one
 * entry nobody instantiates is exactly the one that breaks — and it breaks in a consumer's
 * `composer install` rather than here.
 */
final class EveryRegisteredHookExistsTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function registered(): array
    {
        return array_map(static fn (string $class): array => [$class], HookRegistry::BUILTINS);
    }

    /**
     * @dataProvider registered
     */
    public function test_a_registered_hook_is_a_class_that_exists(string $class): void
    {
        $this->assertTrue(class_exists($class), "{$class} is registered but does not exist — a rename left the import behind");
        $this->assertTrue(is_subclass_of($class, Hook::class), "{$class} is registered but is not a Hook");
    }

    /**
     * The other direction, which is how a shipped hook ends up doing nothing: the file exists, the class
     * is written, and no registry names it — so every layer above reports success over a transport that
     * was never wired.
     */
    public function test_every_handler_written_is_registered(): void
    {
        $unregistered = [];

        foreach (glob(dirname(__DIR__, 2) . '/src/Hooks/Handlers/*.php') ?: [] as $file) {
            $class = 'JesseGall\\CodeCommandments\\Hooks\\Handlers\\' . basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, Hook::class) && ! in_array($class, HookRegistry::BUILTINS, true)) {
                $unregistered[] = basename($file, '.php');
            }
        }

        $this->assertSame([], $unregistered, 'written but never registered, so it silently never runs');
    }
}
