<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * One whole stream, header to trailer, for one request.
 */
final readonly class Stream
{
    public const string VERSION = '1';

    public function __construct(private Request $request) {}

    /**
     * @param  resource  $out
     */
    public function write($out): void
    {
        $files = Sources::in($this->request->paths, $this->request->contents);
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
            $file = $this->parsed($path);
            $this->line($out, ['file' => $file]);
            $referenced += $file->writer->referenced;
            $declared += $file->writer->declared;
        }
        $symbols = (new OutsideSymbols($this->autoload(), $declared, $referenced))->all();
        if ($symbols !== []) {
            $this->line($out, ['program' => ['symbols' => $symbols]]);
        }
        $this->line($out, ['trailer' => ['files' => count($files)]]);
    }

    private function parsed(string $path): ParsedFile
    {
        $code = $this->request->contents[$path] ?? file_get_contents($path);
        $errors = new Collecting();
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $statements = $parser->parse($code, $errors) ?? [];
        $statements = (new NodeTraverser(new NameResolver($errors)))->traverse($statements);
        $writer = new TreeWriter($code);
        $root = $writer->root($statements);

        return new ParsedFile(
            $this->request->shown($path),
            count($errors->getErrors()),
            ! $this->request->judges($path),
            $root,
            $writer->comments($parser->getTokens()),
            $writer,
        );
    }

    /**
     * The scanned project's autoloader: the one asked for, else the nearest vendor/autoload.php above the first path.
     */
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

    /**
     * @param  resource  $out
     */
    private function line($out, array $object): void
    {
        fwrite($out, json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n");
    }
}
