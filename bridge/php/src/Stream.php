<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * One whole stream, header to trailer, for one request.
 */
final readonly class Stream
{
    public const string VERSION = '1';

    /**
     * The mark a file read only for context carries, where its record puts it.
     */
    private const string CONTEXT = ',"context":true';

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
        $kept = $this->request->cache === null ? null : new TreeCache($this->request->cache, $this->request);
        $parser = null;
        foreach ($files as $path) {
            $code = $this->request->contents[$path] ?? file_get_contents($path);
            $context = ! $this->request->judges($path);
            $earlier = $kept?->kept($path, $code);
            if ($earlier !== null) {
                $line = $earlier['line'];
                fwrite($out, ($context ? substr($line, 0, $earlier['at']) . self::CONTEXT . substr($line, $earlier['at']) : $line) . "\n");
                $referenced += $earlier['referenced'];
                $declared += $earlier['declared'];

                continue;
            }
            $parser ??= (new ParserFactory())->createForNewestSupportedVersion();
            $file = $this->parsed($path, $code, $parser);
            $line = $this->encoded(['file' => $file]);
            fwrite($out, $line . "\n");
            $referenced += $file->writer->referenced;
            $declared += $file->writer->declared;
            if ($kept !== null) {
                $at = strlen($this->encoded(['file' => ['path' => $file->path, 'language' => 'php', 'errors' => $file->errors]])) - 2;
                $own = $context ? substr($line, 0, $at) . substr($line, $at + strlen(self::CONTEXT)) : $line;
                $kept->keep($path, $code, $own, $at, $file->writer->referenced, $file->writer->declared);
            }
        }
        $this->writeProgram($out, $kept, $declared, $referenced);
        $this->line($out, ['trailer' => ['files' => count($files)]]);
    }

    private function parsed(string $path, string $code, Parser $parser): ParsedFile
    {
        $errors = new Collecting();
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
     * The line naming the symbols the files reach outside the scan, as kept for the same names when it was, written
     * after the files; no line when they reach none.
     *
     * @param  resource  $out
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $referenced
     */
    private function writeProgram($out, ?TreeCache $kept, array $declared, array $referenced): void
    {
        $autoload = $this->autoload();
        $key = $kept?->programKey($autoload, $declared, $referenced);
        $line = $key === null ? null : $kept->program($key);
        if ($line === null) {
            $outside = new OutsideSymbols($autoload, $declared, $referenced);
            $program = array_filter(['symbols' => $outside->all(), 'unreadable' => $outside->unreadable()]);
            $line = $program === [] ? '' : $this->encoded(['program' => $program]);
            if ($key !== null) {
                $kept->keepProgram($key, $line);
            }
        }
        if ($line !== '') {
            fwrite($out, $line . "\n");
        }
    }

    /**
     * @param  resource  $out
     */
    private function line($out, array $object): void
    {
        fwrite($out, $this->encoded($object) . "\n");
    }

    private function encoded(array $object): string
    {
        return json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
