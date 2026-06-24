<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Support\RegistryShape;
use JesseGall\CodeCommandments\Support\SetShape;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * The corpus-grounded contract for the registry/set role detectors: every real
 * registry variant (array / Collection / wrapper / merge / immutable / weakmap
 * store) is detected, every look-alike (set, cache, repository, config, factory,
 * memoizer, dispatcher, value object) is not, and Registry and Set never overlap.
 */
class RegistryDetectionTest extends TestCase
{
    private const CORPUS = __DIR__ . '/../Fixtures/registry-corpus';

    /**
     * @dataProvider registries
     */
    public function test_real_registries_are_detected(string $file): void
    {
        $this->assertTrue($this->isRegistry($file), basename($file) . ' should be detected as a registry');
    }

    /**
     * @dataProvider lookAlikes
     */
    public function test_look_alikes_are_not_registries(string $file): void
    {
        $this->assertFalse($this->isRegistry($file), basename($file) . ' must not be detected as a registry');
    }

    public function test_registry_and_set_shapes_never_overlap(): void
    {
        foreach (glob(self::CORPUS . '/*/*.php') as $file) {
            $this->assertFalse(
                $this->isRegistry($file) && $this->isSet($file),
                basename($file) . ' is detected as BOTH a Registry and a Set',
            );
        }
    }

    public function test_a_sentinel_member_set_is_a_set_not_a_registry(): void
    {
        $permissionSet = self::CORPUS . '/non-registries/PermissionSet.php';

        $this->assertTrue($this->isSet($permissionSet), 'PermissionSet should be a Set');
        $this->assertFalse($this->isRegistry($permissionSet), 'PermissionSet is a member set, not a registry');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function registries(): iterable
    {
        foreach (glob(self::CORPUS . '/registries/*.php') as $file) {
            yield basename($file, '.php') => [$file];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lookAlikes(): iterable
    {
        foreach (glob(self::CORPUS . '/non-registries/*.php') as $file) {
            yield basename($file, '.php') => [$file];
        }
    }

    private function isRegistry(string $file): bool
    {
        return $this->detect($file, static fn (Node\Stmt\Class_ $c): bool => RegistryShape::detect($c) !== null);
    }

    private function isSet(string $file): bool
    {
        return $this->detect($file, static fn (Node\Stmt\Class_ $c): bool => SetShape::detect($c) !== null);
    }

    private function detect(string $file, callable $predicate): bool
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse((string) file_get_contents($file));

        foreach ((new NodeFinder)->findInstanceOf($ast ?? [], Node\Stmt\Class_::class) as $class) {
            if ($predicate($class)) {
                return true;
            }
        }

        return false;
    }
}
