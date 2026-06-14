<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;

/**
 * Find double-quoted string interpolation (`"...{$x}..."`) and rewrite it to
 * `sprintf()` with `%s` placeholders — separating the template from its values
 * and (by default) pulling invisible escape runs into named T_String constants.
 *
 * The build lives in {@see analyze()} so the judge pipe and the prophet's
 * auto-fixer share one rewrite and can never disagree.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindStringInterpolations implements Pipe
{
    /** Control characters that map to a T_String constant, longest first. */
    private const WHITESPACE_CONSTANTS = [
        "\r\n" => 'CRLF',
        "\n\n" => 'PARAGRAPH',
        "\n" => 'NEWLINE',
        "\r" => 'CARRIAGE_RETURN',
        "\t" => 'TAB',
        "\0" => 'NULL_BYTE',
    ];

    /**
     * @var array{require_escape: bool, extract_whitespace: bool, min_interpolations: int, string_class: string}
     */
    private array $options = [
        'require_escape' => true,
        'extract_whitespace' => true,
        'min_interpolations' => 1,
        'string_class' => 'JesseGall\\PhpTypes\\T_String',
    ];

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, array_intersect_key($options, $this->options));

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $matches = [];

        foreach (self::analyze($input->ast, $input->content, $this->options) as $finding) {
            $matches[] = new MatchResult(
                name: 'interpolation',
                pattern: '',
                match: $finding['snippet'],
                line: $finding['line'],
                offset: null,
                content: $finding['snippet'],
                groups: [
                    'args' => (string) $finding['arg_count'],
                    'sprintf' => $finding['replacement'],
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * @param  array<Node>  $ast
     * @param  array<string, mixed>  $options
     * @return list<array{start: int, end: int, line: int, replacement: string, snippet: string, needs_import: bool, string_class: string, arg_count: int}>
     */
    public static function analyze(array $ast, string $content, array $options): array
    {
        $options = array_merge([
            'require_escape' => true,
            'extract_whitespace' => true,
            'min_interpolations' => 1,
            'string_class' => 'JesseGall\\PhpTypes\\T_String',
        ], $options);

        $findings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Scalar\InterpolatedString::class) as $node) {
            $built = self::build($node, $content, $options);

            if ($built !== null) {
                $findings[] = $built;
            }
        }

        return $findings;
    }

    /**
     * @param  array{require_escape: bool, extract_whitespace: bool, min_interpolations: int, string_class: string}  $options
     * @return array{start: int, end: int, line: int, replacement: string, snippet: string, needs_import: bool, string_class: string, arg_count: int}|null
     */
    private static function build(Scalar\InterpolatedString $node, string $content, array $options): ?array
    {
        // Heredocs are intentional multi-line templates — leave them be.
        if ($node->getAttribute('kind') !== Scalar\String_::KIND_DOUBLE_QUOTED) {
            return null;
        }

        $shortClass = self::shortName($options['string_class']);
        $format = '';
        $args = [];
        $interpolations = 0;
        $hasEscape = false;
        $needsImport = false;

        foreach ($node->parts as $part) {
            if ($part instanceof Node\InterpolatedStringPart) {
                if ($options['extract_whitespace']) {
                    [$pieceFormat, $pieceArgs, $pieceEscape] = self::tokenizeLiteral($part->value, $shortClass);
                    $format .= $pieceFormat;
                    array_push($args, ...$pieceArgs);
                    $hasEscape = $hasEscape || $pieceEscape;
                    $needsImport = $needsImport || $pieceArgs !== [];
                } else {
                    $hasEscape = $hasEscape || preg_match('/[\n\r\t\0]/', $part->value) === 1;
                    $format .= str_replace('%', '%%', $part->value);
                }

                continue;
            }

            $source = self::source($content, $part);

            if ($source === null) {
                return null; // can't extract the interpolated expression
            }

            $interpolations++;
            $format .= '%s';
            $args[] = $source;
        }

        if ($interpolations < max(1, $options['min_interpolations'])) {
            return null;
        }

        if ($options['require_escape'] && ! $hasEscape) {
            return null;
        }

        $start = (int) $node->getStartFilePos();
        $end = (int) $node->getEndFilePos();

        return [
            'start' => $start,
            'end' => $end,
            'line' => $node->getStartLine(),
            'replacement' => self::renderSprintf($format, $args, $options['extract_whitespace'], self::lineIndent($content, $start)),
            'snippet' => self::source($content, $node) ?? '',
            'needs_import' => $needsImport,
            'string_class' => $options['string_class'],
            'arg_count' => count($args),
        ];
    }

    /**
     * Split a literal piece into a format string + T_String constant args,
     * pulling out each run of control characters (longest match first).
     *
     * @return array{0: string, 1: list<string>, 2: bool}  [format, args, hasEscape]
     */
    private static function tokenizeLiteral(string $text, string $shortClass): array
    {
        $format = '';
        $args = [];
        $plain = '';
        $hasEscape = false;
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            $const = null;
            $advance = 1;

            foreach (self::WHITESPACE_CONSTANTS as $sequence => $name) {
                if (substr($text, $i, strlen($sequence)) === $sequence) {
                    $const = $name;
                    $advance = strlen($sequence);
                    break;
                }
            }

            if ($const === null) {
                $plain .= $text[$i];
                $i++;

                continue;
            }

            if ($plain !== '') {
                $format .= str_replace('%', '%%', $plain);
                $plain = '';
            }

            $format .= '%s';
            $args[] = $shortClass . '::' . $const;
            $hasEscape = true;
            $i += $advance;
        }

        if ($plain !== '') {
            $format .= str_replace('%', '%%', $plain);
        }

        return [$format, $args, $hasEscape];
    }

    /**
     * @param  list<string>  $args
     */
    private static function renderSprintf(string $format, array $args, bool $extractWhitespace, string $baseIndent): string
    {
        $literal = $extractWhitespace
            ? "'" . strtr($format, ['\\' => '\\\\', "'" => "\\'"]) . "'"
            : '"' . strtr($format, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', "\0" => '\\0']) . '"';

        if ($args === []) {
            return 'sprintf(' . $literal . ')';
        }

        if (count($args) === 1) {
            return 'sprintf(' . $literal . ', ' . $args[0] . ')';
        }

        $inner = $baseIndent . '    ';
        $lines = [$inner . $literal . ','];

        foreach ($args as $arg) {
            $lines[] = $inner . $arg . ',';
        }

        return "sprintf(\n" . implode("\n", $lines) . "\n" . $baseIndent . ')';
    }

    /**
     * The leading whitespace of the line the offset sits on.
     */
    private static function lineIndent(string $content, int $position): string
    {
        $newline = strrpos(substr($content, 0, $position), "\n");
        $lineStart = $newline === false ? 0 : $newline + 1;

        preg_match('/\A[ \t]*/', substr($content, $lineStart), $match);

        return $match[0] ?? '';
    }

    private static function source(string $content, Node $node): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start === null || $end === null || $start < 0 || $end < $start) {
            return null;
        }

        return substr($content, $start, $end - $start + 1);
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
