<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** ValueFlow's verdict on every field a class-like declares: where its value is assumed set, where it is guarded. */
final class ValueFlows implements Question
{
    public function name(): string
    {
        return 'valueflow';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $flow = $codebase->valueFlow();
        foreach (nodes($file->ast) as $node) {
            if (! $node instanceof Node\Stmt\ClassLike || $node->namespacedName === null) {
                continue;
            }
            $class = $node->namespacedName->toString();
            foreach ($codebase->wrap($node, $file)->fields() as $field) {
                $verdict = $flow->verdict($class, $field->name);
                $explained = $flow->explain($class, $field->name);
                yield [$node, $field->name, [
                    'assume' => $verdict->assume,
                    'guard' => $verdict->guard,
                    'assumed' => array_map(self::located(...), $explained['assume']),
                    'guarded' => array_map(self::located(...), $explained['guard']),
                    'chain' => $flow->chainPath($class, $field->name),
                ]];
            }
        }
    }

    private static function located(string $location): string
    {
        return substr($location, strlen($GLOBALS['oracleRoot']) + 1);
    }
}
