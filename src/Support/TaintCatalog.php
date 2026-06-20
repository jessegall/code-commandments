<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * The security vocabulary for the taint prophets (#163 #6/#8): which expressions are
 * SOURCES (request input, secrets), which calls are dangerous/leak SINKS, and which
 * are SANITIZER/REDACTION boundaries. This is the one place a name list IS the real
 * signal — a taint source/sink is DEFINED by its API name, so matching the name is
 * correct, not a heuristic.
 *
 * Used for DIRECT-flow detection: a source appearing inside a sink's argument with no
 * boundary in that argument is unambiguous taint, no flow tracer required.
 */
final class TaintCatalog
{
    /** Methods on `request()` / `$request` / `Input::` that return user input. */
    private const REQUEST_METHODS = ['input', 'query', 'post', 'get', 'all', 'old', 'cookie', 'header', 'json', 'string', 'boolean', 'integer'];

    /** `Foo::raw(...)` style raw-SQL sinks. */
    private const RAW_SQL_STATIC = ['raw', 'statement', 'unprepared', 'select', 'insert', 'update', 'delete'];

    /** `->whereRaw(...)` style raw-SQL builder sinks. */
    private const RAW_SQL_METHODS = ['whereraw', 'orwhereraw', 'havingraw', 'orhavingraw', 'orderbyraw', 'selectraw', 'groupbyraw', 'fromraw'];

    /** Dangerous global-function sinks (command / deserialization). */
    private const DANGEROUS_FUNCS = ['exec', 'shell_exec', 'system', 'proc_open', 'passthru', 'popen', 'unserialize'];

    /** Leak sinks (log / dump). */
    private const LEAK_FUNCS = ['logger', 'info', 'dd', 'dump', 'var_dump', 'print_r', 'var_export', 'logging'];

    private const LOG_STATIC = ['info', 'error', 'warning', 'debug', 'notice', 'critical', 'alert', 'emergency', 'log'];

    /** Calls/casts that neutralise tainted input. */
    private const SANITIZER_FUNCS = ['intval', 'floatval', 'boolval', 'in_array', 'array_key_exists', 'validated', 'e', 'htmlspecialchars', 'htmlentities', 'bin2hex', 'abs', 'count'];

    /** Calls that redact a secret before it reaches a leak sink. */
    private const REDACTION_FUNCS = ['bcrypt', 'sha1', 'md5', 'hash', 'str_repeat', 'substr', 'mask', 'encrypt', 'make'];

    /** Property names that hold a secret. */
    private const SECRET_PROPS = ['password', 'secret', 'token', 'apitoken', 'apikey', 'accesstoken', 'refreshtoken', 'privatekey', 'clientsecret'];

    // ---- Sources -----------------------------------------------------------

    public function isRequestSource(Node $node): bool
    {
        // request('x') with an argument
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'request' && $node->getArgs() !== []
        ) {
            return true;
        }

        // request()->input(...) / $request->input(...) / Input::get(...)
        if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::REQUEST_METHODS, true)
            && $this->isRequestReceiver($node->var)
        ) {
            return true;
        }

        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && $node->class->getLast() === 'Input' && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::REQUEST_METHODS, true)
        ) {
            return true;
        }

        // $request->something (a dynamic input property)
        return $node instanceof Node\Expr\PropertyFetch && $this->isRequestVar($node->var);
    }

    public function isSecretSource(Node $node): bool
    {
        // config('...secret/key/token/password...')
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'config'
        ) {
            $arg = $node->getArgs()[0]->value ?? null;

            return $arg instanceof Node\Scalar\String_
                && (bool) preg_match('/(password|secret|token|api[_-]?key|credential|private[_-]?key)/i', $arg->value);
        }

        // $x->password / $x->apiToken / ...
        return $node instanceof Node\Expr\PropertyFetch
            && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::SECRET_PROPS, true);
    }

    // ---- Sinks -------------------------------------------------------------

    public function isDangerousSink(Node $node): bool
    {
        return $this->isExecSink($node) || $this->isRawSqlSink($node);
    }

    /** A command/deserialization sink — EVERY argument is dangerous. */
    public function isExecSink(Node $node): bool
    {
        return $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && in_array(strtolower($node->name->toString()), self::DANGEROUS_FUNCS, true);
    }

    /**
     * A raw-SQL sink — only the FIRST argument (the SQL string) is dangerous; any
     * later argument is a BOUND parameter (safe), so callers must check arg 0 only.
     */
    public function isRawSqlSink(Node $node): bool
    {
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && $node->class->getLast() === 'DB' && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::RAW_SQL_STATIC, true)
        ) {
            return true;
        }

        return $node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::RAW_SQL_METHODS, true);
    }

    public function isLeakSink(Node $node): bool
    {
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && in_array(strtolower($node->name->toString()), self::LEAK_FUNCS, true)
        ) {
            return true;
        }

        return $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && $node->class->getLast() === 'Log' && $node->name instanceof Node\Identifier
            && in_array(strtolower($node->name->toString()), self::LOG_STATIC, true);
    }

    // ---- Boundaries --------------------------------------------------------

    /** Whether $arg contains a cast or sanitizer call anywhere (a boundary that neutralises taint). */
    public function hasSanitizer(Node $arg, NodeFinder $finder): bool
    {
        return $this->containsCast($arg, $finder) || $this->containsCall($arg, $finder, self::SANITIZER_FUNCS);
    }

    /** Whether $arg contains a cast or redaction call (a boundary that protects a secret). */
    public function hasRedaction(Node $arg, NodeFinder $finder): bool
    {
        return $this->containsCast($arg, $finder) || $this->containsCall($arg, $finder, self::REDACTION_FUNCS);
    }

    private function containsCast(Node $arg, NodeFinder $finder): bool
    {
        return $finder->findFirstInstanceOf([$arg], Node\Expr\Cast::class) !== null;
    }

    /**
     * @param  list<string>  $names
     */
    private function containsCall(Node $arg, NodeFinder $finder, array $names): bool
    {
        foreach ($finder->find([$arg], fn (Node $n) => $n instanceof Node\Expr\FuncCall || $n instanceof Node\Expr\MethodCall || $n instanceof Node\Expr\StaticCall) as $call) {
            $name = match (true) {
                $call instanceof Node\Expr\FuncCall && $call->name instanceof Node\Name => strtolower($call->name->getLast()),
                ($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\StaticCall) && $call->name instanceof Node\Identifier => strtolower($call->name->toString()),
                default => '',
            };

            if (in_array($name, $names, true)) {
                return true;
            }
        }

        return false;
    }

    private function isRequestReceiver(Node $node): bool
    {
        return $this->isRequestVar($node)
            || ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && strtolower($node->name->toString()) === 'request');
    }

    private function isRequestVar(Node $node): bool
    {
        return $node instanceof Node\Expr\Variable && is_string($node->name)
            && in_array(strtolower($node->name), ['request', 'req'], true);
    }
}
