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
        $folder = $this->nearest('vendor/autoload.php');

        return $folder === null ? null : realpath("{$folder}/vendor/autoload.php");
    }

    /**
     * The nearest folder at or above the first scanned path holding the file, or none anywhere above it.
     */
    private function nearest(string $file): ?string
    {
        $folder = is_dir($this->request->paths[0]) ? $this->request->paths[0] : dirname($this->request->paths[0]);
        while (true) {
            if (is_file("{$folder}/{$file}")) {
                return $folder;
            }
            if (dirname($folder) === $folder) {
                return null;
            }
            $folder = dirname($folder);
        }
    }

    /**
     * Why the project's own loader was not used, for the program line to carry, or none when it was. A project that
     * declares dependencies it has not installed reads no class of a package, so a method a package's interface
     * fixes reads as the class's own and a rule that excuses such a method cannot: the run says so rather than
     * letting the finding look like the rule's mistake. PHP's own declarations are still read either way.
     */
    private function loaderReason(?string $autoload, string $failure, OutsideSymbols $outside): string
    {
        if ($outside->unlisted() === []) {
            return '';
        }
        if ($autoload !== null) {
            return $failure === '' ? '' : "the project's autoloader at {$autoload} does not load here: {$failure}";
        }
        $folder = $this->nearest('composer.json');
        if ($folder === null || ! $this->declaresDependencies("{$folder}/composer.json")) {
            return '';
        }

        return 'composer.json declares dependencies but vendor/autoload.php is not there: run composer install';
    }

    /**
     * Whether the composer.json names a package to install.
     */
    private function declaresDependencies(string $path): bool
    {
        $declared = json_decode((string) file_get_contents($path), true);
        if (! is_array($declared)) {
            return false;
        }
        foreach (['require', 'require-dev'] as $section) {
            if (is_array($declared[$section] ?? null) && $declared[$section] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why the autoloader does not load on this machine, tried in a PHP of its own, or none when it loads: one that
     * requires a file only another machine has dies where no handler can catch it, and the run then reads PHP's own
     * declarations alone.
     */
    private function loadFailure(string $autoload): string
    {
        $tried = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-r', 'require $argv[1];', $autoload], [1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
        if ($tried === false) {
            return 'it could not be tried here';
        }
        $failure = trim((string) stream_get_contents($pipes[2]));
        if (proc_close($tried) === 0) {
            return '';
        }
        if ($failure === '') {
            return 'it failed to load';
        }

        return $failure;
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
            $failure = $autoload === null ? '' : $this->loadFailure($autoload);
            $outside = new OutsideSymbols($autoload !== null && $failure === '' ? $autoload : null, $declared, $referenced);
            $program = array_filter([
                'symbols' => $outside->all(),
                'unreadable' => $outside->unreadable(),
                'loader' => $this->loaderReason($autoload, $failure, $outside),
            ]);
            $line = $program === [] ? '' : $this->encoded(['program' => $program]);
            if ($key !== null && ($loads || $autoload === null)) {
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
