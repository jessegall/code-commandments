<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Packages\Exemption;
use PhpParser\Node;

/**
 * What the shipped packages excuse, under each tag: every declared class and each of its methods, every attribute,
 * and every fully qualified name the shop writes.
 */
final class Exemptions implements Question
{
    public function name(): string
    {
        return 'exemptions';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $exemptions = $codebase->exemptions();
        $tags = array_map(static fn (string $class): Exemption => new $class(), Exemption::all());
        $ask = static function (callable $has) use ($tags): array {
            $answer = [];
            foreach ($tags as $tag) {
                $answer[$tag->slug()] = $has($tag::class);
            }
            ksort($answer);

            return $answer;
        };
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
                $class = $node->namespacedName->toString();
                yield [$node, $class, $ask(static fn (string $tag): bool => $exemptions->has($tag, $codebase, $class))];
                foreach ($node->getMethods() as $method) {
                    $name = $method->name->toString();
                    yield [$method, [$class, $name], $ask(static fn (string $tag): bool => $exemptions->has($tag, $codebase, $class, $name))];
                }
            }
            if ($node instanceof Node\Attribute) {
                $attribute = $node->name->toString();
                yield [$node, $attribute, $ask(static fn (string $tag): bool => $exemptions->hasAttribute($tag, $codebase, $attribute))];
            }
            if ($node instanceof Node\Name\FullyQualified) {
                $name = $node->toString();
                yield [$node, $name, $ask(static fn (string $tag): bool => $exemptions->has($tag, $codebase, $name))];
            }
        }
    }
}
