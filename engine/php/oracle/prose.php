<?php

/**
 * Writes what the PHP tool reads in comment text, for every comment in a tree and every line of prose-probes.txt:
 * php prose.php <root> > prose.json
 */

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Support\CommentedCode;
use JesseGall\CodeCommandments\Ast\Support\Docblock;
use JesseGall\CodeCommandments\Support\Prose;

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../bridge/php/src/Sources.php';

$texts = [];
foreach (\CodeCommandments\PhpBridge\Sources::in([$argv[1]]) as $path) {
    foreach (\PhpToken::tokenize((string) file_get_contents($path)) as $token) {
        if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
            $texts[] = rtrim($token->text, "\r\n");
        }
    }
}
foreach (file(__DIR__ . '/prose-probes.txt', FILE_IGNORE_NEW_LINES) as $line) {
    $texts[] = $line;
}
$probe = '';
foreach (file(__DIR__ . '/prose-probes.txt', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, '/**')) {
        $probe = $line;
    } elseif ($probe !== '') {
        $probe .= "\n" . $line;
    }
    if ($probe !== '' && str_ends_with($line, '*/')) {
        $texts[] = $probe;
        $probe = '';
    }
}
$texts = array_values(array_unique($texts));

echo json_encode(array_map(static function (string $text): array {
    preg_match_all('/\{@(?:see|link)\s+\\\\?([A-Za-z_][\w\\\\]*\\\\[\w\\\\]+)/', $text, $references);
    $lines = array_map(static fn (string $line) => trim(ltrim(trim($line), '/*')), preg_split('/\R/', $text) ?: []);

    return [
        'text' => $text,
        'history' => preg_match(Prose::HISTORY, $text) === 1,
        'strawman' => preg_match(Prose::strawman(), $text) === 1,
        'words' => Prose::words($text),
        'code' => CommentedCode::isCode($text),
        'inline' => Docblock::isInline($text),
        'references' => array_values(array_unique($references[1])),
        'paragraphs' => Prose::paragraphs($lines, static fn (string $line): bool => ! str_starts_with($line, '@')),
    ];
}, $texts), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
