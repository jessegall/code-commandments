<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commandments;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Error as ParseError;
use ReflectionClass;

/**
 * Base class for PHP file commandments.
 * Provides AST-based analysis using nikic/php-parser.
 */
abstract class PhpCommandment extends BaseCommandment
{
    protected ?\PhpParser\Parser $parser = null;

    /**
     * Map of type aliases to their base classes for reflection checking.
     */
    protected const TYPE_MAP = [
        'controller' => 'Illuminate\\Routing\\Controller',
        'model' => 'Illuminate\\Database\\Eloquent\\Model',
        'request' => 'Illuminate\\Foundation\\Http\\FormRequest',
        'resource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        'job' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
        'command' => 'Illuminate\\Console\\Command',
        'provider' => 'Illuminate\\Support\\ServiceProvider',
        'rule' => 'Illuminate\\Contracts\\Validation\\Rule',
        'data' => 'Spatie\\LaravelData\\Data',
    ];

    public function applicableExtensions(): array
    {
        return ['php'];
    }

    /**
     * Get the PHP parser instance.
     */
    protected function getParser(): \PhpParser\Parser
    {
        if ($this->parser === null) {
            $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        }

        return $this->parser;
    }

    /**
     * Parse PHP content into AST nodes.
     *
     * @return array<Node>|null Returns null if parsing fails
     */
    protected function parse(string $content): ?array
    {
        try {
            return $this->getParser()->parse($content);
        } catch (ParseError) {
            return null;
        }
    }

    /**
     * Traverse AST with a visitor.
     *
     * @param array<Node> $ast
     */
    protected function traverse(array $ast, NodeVisitorAbstract $visitor): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
    }

    /**
     * Find all nodes of a specific type.
     *
     * @template T of Node
     * @param array<Node> $ast
     * @param class-string<T> $nodeType
     * @return array<T>
     */
    protected function findNodes(array $ast, string $nodeType): array
    {
        $found = [];
        $visitor = new class($nodeType, $found) extends NodeVisitorAbstract {
            /** @var array<Node> */
            private array $found;
            private string $nodeType;

            /**
             * @param class-string<Node> $nodeType
             * @param array<Node> $found
             */
            public function __construct(string $nodeType, array &$found)
            {
                $this->nodeType = $nodeType;
                $this->found = &$found;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof $this->nodeType) {
                    $this->found[] = $node;
                }

                return null;
            }
        };

        $this->traverse($ast, $visitor);

        return $found;
    }

    /**
     * Check if the file contains a class of a specific Laravel type.
     *
     * Uses PHP reflection when the class is autoloadable to check the full
     * inheritance chain. Falls back to AST-based string matching otherwise.
     */
    protected function isLaravelClass(array $ast, string $type): bool
    {
        $baseClass = self::TYPE_MAP[$type] ?? $type;
        $fqcn = $this->getFullyQualifiedClassName($ast);

        // Try reflection first if the class exists
        if ($fqcn !== null && class_exists($fqcn)) {
            return $this->isInstanceOfClass($fqcn, $baseClass);
        }

        // Fall back to AST-based checking for non-autoloadable classes
        return $this->isLaravelClassByAst($ast, $type);
    }

    /**
     * Check if a class is an instance of another class using reflection.
     */
    protected function isInstanceOfClass(string $fqcn, string $baseClass): bool
    {
        try {
            $reflection = new ReflectionClass($fqcn);

            // Check if it's the base class itself
            if ($reflection->getName() === $baseClass) {
                return true;
            }

            // Check if it extends the base class
            if ($reflection->isSubclassOf($baseClass)) {
                return true;
            }

            // Check if it implements the interface (for contracts like ShouldQueue)
            if (interface_exists($baseClass) && $reflection->implementsInterface($baseClass)) {
                return true;
            }

            return false;
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if the file is a specific type of Laravel class using AST only.
     *
     * This is the fallback when reflection is not available.
     */
    protected function isLaravelClassByAst(array $ast, string $type): bool
    {
        $typeMap = [
            'controller' => ['Controller', 'Illuminate\\Routing\\Controller'],
            'model' => ['Model', 'Illuminate\\Database\\Eloquent\\Model'],
            'request' => ['FormRequest', 'Illuminate\\Foundation\\Http\\FormRequest'],
            'resource' => ['JsonResource', 'Illuminate\\Http\\Resources\\Json\\JsonResource'],
            'job' => ['ShouldQueue', 'Illuminate\\Contracts\\Queue\\ShouldQueue'],
            'event' => ['Event'],
            'listener' => ['ShouldQueue'],
            'command' => ['Command', 'Illuminate\\Console\\Command'],
            'middleware' => ['Middleware'],
            'policy' => ['Policy'],
            'provider' => ['ServiceProvider', 'Illuminate\\Support\\ServiceProvider'],
            'rule' => ['Rule', 'Illuminate\\Contracts\\Validation\\Rule'],
            'data' => ['Data', 'Spatie\\LaravelData\\Data'],
        ];

        $parentClasses = $typeMap[$type] ?? [$type];

        foreach ($this->findNodes($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->extends !== null) {
                $extends = $class->extends->toString();
                foreach ($parentClasses as $parentClass) {
                    if ($extends === $parentClass || str_ends_with($extends, '\\' . $parentClass)) {
                        return true;
                    }
                }
            }

            // Also check implements
            foreach ($class->implements as $interface) {
                $implements = $interface->toString();
                foreach ($parentClasses as $parentClass) {
                    if ($implements === $parentClass || str_ends_with($implements, '\\' . $parentClass)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get the class name from AST.
     */
    protected function getClassName(array $ast): ?string
    {
        foreach ($this->findNodes($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name !== null) {
                return $class->name->toString();
            }
        }

        return null;
    }

    /**
     * Get the namespace from AST.
     */
    protected function getNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_ && $node->name !== null) {
                return $node->name->toString();
            }
        }

        return null;
    }

    /**
     * Get the fully qualified class name from AST.
     */
    protected function getFullyQualifiedClassName(array $ast): ?string
    {
        $namespace = $this->getNamespace($ast);
        $className = $this->getClassName($ast);

        if ($className === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $className : $className;
    }
}
