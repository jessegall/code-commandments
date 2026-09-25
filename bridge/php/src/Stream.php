<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/** One whole stream, header to trailer, for one request. */
final readonly class Stream
{
    public const string VERSION = '1';

    public function __construct(private Request $request) {}

    /** @param  resource  $out */
    public function write($out): void
    {
        $files = Sources::in($this->request->paths);
        $this->line($out, ['header' => [
            'contract' => 'tree',
            'version' => 1,
            'language' => 'php',
            'bridge' => ['name' => 'php-bridge', 'version' => self::VERSION],
            'roots' => array_map($this->request->shown(...), $this->request->paths),
        ]]);
        $referenced = [];
        $declared = [];
        foreach ($files as $path) {
            $writer = $this->parsed($path, $file);
            $this->line($out, ['file' => $file]);
            $referenced += $writer->referenced;
            $declared += $writer->declared;
        }
        $symbols = (new OutsideSymbols($this->autoload(), $declared, $referenced))->all();
        if ($symbols !== []) {
            $this->line($out, ['program' => ['symbols' => $symbols]]);
        }
        $this->line($out, ['trailer' => ['files' => count($files)]]);
    }

    /** @param-out array<string, mixed> $file */
    private function parsed(string $path, ?array &$file): TreeWriter
    {
        $code = file_get_contents($path);
        $errors = new Collecting();
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $statements = $parser->parse($code, $errors) ?? [];
        $statements = (new NodeTraverser(new NameResolver($errors)))->traverse($statements);
        $writer = new TreeWriter($code);
        $file = ['path' => $this->request->shown($path), 'language' => 'php', 'errors' => count($errors->getErrors())];
        if (! $this->request->judges($path)) {
            $file['context'] = true;
        }
        $file['root'] = $writer->root($statements);
        $file['comments'] = $writer->comments($parser->getTokens());

        return $writer;
    }

    /** The scanned project's autoloader: the one asked for, else the nearest vendor/autoload.php above the first path. */
    private function autoload(): ?string
    {
        if ($this->request->autoload !== null) {
            return $this->request->autoload;
        }
        $folder = is_dir($this->request->paths[0]) ? $this->request->paths[0] : dirname($this->request->paths[0]);
        while (true) {
            if (is_file("{$folder}/vendor/autoload.php")) {
                return realpath("{$folder}/vendor/autoload.php");
            }
            if (dirname($folder) === $folder) {
                return null;
            }
            $folder = dirname($folder);
        }
    }

    /** @param  resource  $out */
    private function line($out, array $object): void
    {
        fwrite($out, json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n");
    }
}
