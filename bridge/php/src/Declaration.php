<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Node;
use ReflectionClass;

/**
 * What php-parser can emit, read from the parser itself: every node class under PhpParser\Node answers its own
 * getType() and getSubNodeNames(), which ARE the kind and the field names this bridge writes.
 */
final readonly class Declaration
{
    /**
     * The folder php-parser's node classes live in.
     */
    private const string NODES = __DIR__ . '/../parser/lib/PhpParser/Node';

    /**
     * @return array{language: string, parser: string, from: string, kinds: list<array{kind: string, fields?: list<string>}>}
     */
    public static function written(): array
    {
        $kinds = [];
        foreach (self::classes() as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface() || ! $reflection->implementsInterface(Node::class)) {
                continue;
            }
            $node = $reflection->newInstanceWithoutConstructor();
            assert($node instanceof Node);
            $kind = $node->getType();
            $fields = $node->getSubNodeNames();
            $kinds[$kind] = ['kind' => $kind] + ($fields === [] ? [] : ['fields' => $fields]);
        }

        // The file's own root is this bridge's, not php-parser's: a parsed file is a list of statements, and the
        // stream gives them a root to hang from. It is declared here because the stream really writes it.
        $kinds['File'] = ['kind' => 'File', 'fields' => ['stmts']];

        ksort($kinds);
        foreach ($kinds as &$declared) {
            if (isset($declared['fields'])) {
                sort($declared['fields']);
            }
        }

        return [
            'language' => 'php',
            'parser' => 'php-parser',
            'from' => "php-parser's own node classes: each one's getType() and getSubNodeNames()",
            'kinds' => array_values($kinds),
        ];
    }

    /**
     * Every class name under PhpParser\Node, by the path its file sits at.
     *
     * @return list<class-string>
     */
    private static function classes(): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::NODES, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $within = substr($file->getPathname(), strlen(self::NODES) + 1, -4);
            $class = 'PhpParser\\Node\\' . str_replace('/', '\\', $within);
            if (class_exists($class)) {
                $found[] = $class;
            }
        }

        return $found;
    }
}
