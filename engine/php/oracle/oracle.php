<?php

/**
 * Asks the PHP engine's analyses about every node of a codebase and writes each question's answers as JSON lines,
 * `<out>/<question>.jsonl`: the Go port is held to them. Usage: php oracle.php <root> <out> [question...]
 */

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

require __DIR__ . '/../../../vendor/autoload.php';
foreach (glob(__DIR__ . '/questions/*.php') as $question) {
    require_once $question;
}

/** One thing asked of every file, answered per node. */
interface Question
{
    public function name(): string;

    /**
     * Each answer: the node asked about, what was asked of it (null when only the node), and the PHP engine's answer.
     *
     * @return iterable<array{0: Node, 1: mixed, 2: mixed}>
     */
    public function answers(Codebase $codebase, ParsedFile $file): iterable;
}

/** Every node of the file, in pre-order: the order the bridge numbers them in. */
function nodes(array $ast): iterable
{
    foreach ($ast as $node) {
        if (! $node instanceof Node) {
            continue;
        }
        yield $node;
        foreach ($node->getSubNodeNames() as $name) {
            yield from nodes(is_array($node->$name) ? $node->$name : [$node->$name]);
        }
    }
}

[, $root, $out] = $argv + [null, null, null];
if ($root === null || $out === null) {
    fwrite(STDERR, "usage: php oracle.php <root> <out> [question...]\n");
    exit(1);
}
$root = realpath($root);
$asked = array_slice($argv, 3);
$questions = array_values(array_filter(
    array_map(static fn (string $class): Question => new $class(), array_filter(get_declared_classes(), static fn (string $class): bool => is_subclass_of($class, Question::class))),
    static fn (Question $question): bool => $asked === [] || in_array($question->name(), $asked, true),
));
usort($questions, static fn (Question $a, Question $b): int => $a->name() <=> $b->name());

$codebase = Codebase::scan($root);
$files = $codebase->files();
usort($files, static fn (ParsedFile $a, ParsedFile $b): int => $a->path <=> $b->path);
@mkdir($out, 0o755, true);
foreach ($questions as $question) {
    $handle = fopen("{$out}/{$question->name()}.jsonl", 'w');
    foreach ($files as $file) {
        $relative = substr(realpath($file->path), strlen($root) + 1);
        foreach ($question->answers($codebase, $file) as [$node, $ask, $answer]) {
            fwrite($handle, json_encode([
                'file' => $relative,
                'span' => [$node->getStartFilePos(), $node->getEndFilePos() + 1],
                'kind' => $node->getType(),
                'ask' => $ask,
                'answer' => $answer,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n");
        }
    }
    fclose($handle);
}
