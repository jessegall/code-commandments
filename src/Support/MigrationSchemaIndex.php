<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * The declared database schema mined from a project's migrations — table → column →
 * type category. Standalone (database/migrations/ is OUTSIDE the scanned scroll), it
 * walks up to the composer.json root and parses every migration's
 * `Schema::create('t', fn (Blueprint $t) => …)` / `Schema::table(...)` once.
 *
 * Each Blueprint column method maps to a coarse TYPE CATEGORY (json / bool / datetime
 * / decimal / int / string); alters merge onto the created table. Backs
 * MigrationModelDriftProphet (#163 #3) so a model can be checked against the real
 * columns its table declares. Pure Blueprint AST — no Laravel runtime, no name lists
 * beyond the framework's own column-method vocabulary.
 */
final class MigrationSchemaIndex
{
    /** @var array<string, self> */
    private static array $cache = [];

    /** Blueprint column method (lowercased) → coarse type category. */
    private const COLUMN_TYPES = [
        'json' => 'json', 'jsonb' => 'json',
        'boolean' => 'bool', 'bool' => 'bool',
        'datetime' => 'datetime', 'datetimetz' => 'datetime', 'timestamp' => 'datetime',
        'timestamptz' => 'datetime', 'date' => 'datetime', 'time' => 'datetime', 'timetz' => 'datetime', 'year' => 'datetime',
        'decimal' => 'decimal', 'float' => 'decimal', 'double' => 'decimal', 'unsigneddecimal' => 'decimal',
        'integer' => 'int', 'biginteger' => 'int', 'tinyinteger' => 'int', 'smallinteger' => 'int',
        'mediuminteger' => 'int', 'unsignedinteger' => 'int', 'unsignedbiginteger' => 'int',
        'unsignedtinyinteger' => 'int', 'unsignedsmallinteger' => 'int', 'unsignedmediuminteger' => 'int', 'foreignid' => 'int',
        'string' => 'string', 'char' => 'string', 'text' => 'string', 'longtext' => 'string',
        'mediumtext' => 'string', 'tinytext' => 'string', 'uuid' => 'string', 'ulid' => 'string',
        'foureignuuid' => 'string', 'foreignuuid' => 'string', 'foreignulid' => 'string',
        'ipaddress' => 'string', 'macaddress' => 'string', 'enum' => 'string', 'set' => 'string', 'binary' => 'string',
    ];

    /**
     * @param  array<string, array<string, string>>  $tables  table => (column => type category), lowercased
     */
    private function __construct(private readonly array $tables) {}

    public static function forFile(string $filePath): self
    {
        $dir = self::locateMigrationsDir($filePath);

        if ($dir === null) {
            return new self([]);
        }

        return self::$cache[$dir] ??= self::parseDir($dir);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public function isEmpty(): bool
    {
        return $this->tables === [];
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[strtolower($table)]);
    }

    /**
     * Column → type category for $table (empty if the table is unknown).
     *
     * @return array<string, string>
     */
    public function columnsOf(string $table): array
    {
        return $this->tables[strtolower($table)] ?? [];
    }

    private static function locateMigrationsDir(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = '';

        while ($dir !== $previous && $dir !== '' && $dir !== '.') {
            if (is_file($dir . '/composer.json') && is_dir($dir . '/database/migrations')) {
                return $dir . '/database/migrations';
            }

            $previous = $dir;
            $dir = \dirname($dir);
        }

        return null;
    }

    private static function parseDir(string $dir): self
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $tables = [];

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            try {
                $ast = $parser->parse((string) file_get_contents($file));
            } catch (\Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($ast, Node\Expr\StaticCall::class) as $call) {
                if (! $call->class instanceof Node\Name || $call->class->getLast() !== 'Schema'
                    || ! $call->name instanceof Node\Identifier
                    || ! in_array(strtolower($call->name->toString()), ['create', 'table'], true)
                ) {
                    continue;
                }

                $args = $call->getArgs();
                $table = ($args[0]->value ?? null) instanceof Node\Scalar\String_ ? strtolower($args[0]->value->value) : null;
                $closure = $args[1]->value ?? null;

                if ($table === null || ! $closure instanceof Node\Expr\Closure && ! $closure instanceof Node\Expr\ArrowFunction) {
                    continue;
                }

                $tables[$table] ??= [];
                self::collectColumns($closure, $finder, $tables[$table]);
            }
        }

        return new self($tables);
    }

    /**
     * @param  array<string, string>  $columns
     */
    private static function collectColumns(Node\FunctionLike $closure, NodeFinder $finder, array &$columns): void
    {
        $body = $closure instanceof Node\Expr\ArrowFunction ? [$closure->expr] : $closure->getStmts();

        foreach ($finder->findInstanceOf($body, Node\Expr\MethodCall::class) as $mc) {
            if (! $mc->name instanceof Node\Identifier) {
                continue;
            }

            $method = strtolower($mc->name->toString());

            // Structural shorthands declare fixed columns.
            if ($method === 'timestamps' || $method === 'timestampstz' || $method === 'noactiontimestamps') {
                $columns['created_at'] = 'datetime';
                $columns['updated_at'] = 'datetime';

                continue;
            }

            if ($method === 'softdeletes' || $method === 'softdeletestz') {
                $columns['deleted_at'] = 'datetime';

                continue;
            }

            if ($method === 'id') {
                $columns['id'] = 'int';

                continue;
            }

            if ($method === 'remembertoken') {
                $columns['remember_token'] = 'string';

                continue;
            }

            if (! isset(self::COLUMN_TYPES[$method])) {
                continue;
            }

            $first = $mc->getArgs()[0]->value ?? null;

            if ($first instanceof Node\Scalar\String_) {
                $columns[strtolower($first->value)] = self::COLUMN_TYPES[$method];
            }
        }
    }
}
