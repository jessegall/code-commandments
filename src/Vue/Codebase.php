<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\WorkingCopy;
use Closure;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The frontend twin of the backend {@see \JesseGall\CodeCommandments\Ast\Codebase}:
 * a parsed set of `.vue` components, with the SAME fluent selectors that open a
 * {@see Query}. The element nodes of every template are flattened once and cached,
 * so a selector filters a list rather than re-walking each tree.
 */
final class Codebase implements \JesseGall\CodeCommandments\Codebase
{
    /**
     * @var list<array{0: Element, 1: Sfc}>|null
     */
    private ?array $nodes = null;

    /**
     * @var list<TypeDeclaration>|null
     */
    private ?array $typeDeclarations = null;

    /**
     * @param  list<Sfc>  $components
     * @param  list<TypeDeclaration>  $standaloneTypes  types declared in `.ts` files (not in a component)
     */
    private function __construct(
        private readonly array $components,
        private readonly array $standaloneTypes = [],
    ) {}

    public static function fromString(string $vue, string $path = 'component.vue'): self
    {
        return new self([Sfc::parse($vue, $path)]);
    }

    /**
     * Parse every `.vue` file under the given root(s).
     *
     * @param  string|list<string>  $path
     * @param  WorkingCopy  $overlay  pending edits to read THROUGH (empty = straight off disk)
     */
    public static function scan(string|array $path, WorkingCopy $overlay = new WorkingCopy(), ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $vue = [];
        $typeScript = [];

        foreach ((array) $path as $root) {
            foreach (self::filesIn($root, 'vue', $excluded) as $file) {
                $vue[$file] = true;
            }

            foreach (self::filesIn($root, 'ts', $excluded) as $file) {
                $typeScript[$file] = true;
            }

            foreach ($overlay->createdUnder($root, '.vue') as $file) {
                $vue[$file] = true;
            }

            foreach ($overlay->createdUnder($root, '.ts') as $file) {
                $typeScript[$file] = true;
            }
        }

        $components = [];

        foreach (array_keys($vue) as $file) {
            $source = $overlay->read($file);

            if ($source !== null) {
                $components = [...$components, ...self::readable(static fn (): array => [Sfc::parse($source, $file)], $file)];
            }
        }

        $standaloneTypes = [];

        foreach (array_keys($typeScript) as $file) {
            $source = $overlay->read($file);

            if ($source !== null) {
                $standaloneTypes = [...$standaloneTypes, ...self::readable(
                    static fn (): array => TypeDeclaration::fromScript(new Script($source), $file, $source),
                    $file,
                )];
            }
        }

        return new self($components, $standaloneTypes);
    }

    /**
     * What $read yields for one file, or nothing when reading it throws — and a line naming the file
     * so a skip is visible rather than silent. Reading ONE file must never end a scan of thousands:
     * a real tree carries the odd fixture written to be invalid, a half-finished edit, a generated
     * bundle no hand-written grammar was meant to meet.
     *
     * @template T
     * @param  callable(): list<T>  $read
     * @return list<T>
     */
    private static function readable(callable $read, string $file): array
    {
        try {
            return $read();
        } catch (\Throwable $failure) {
            fwrite(STDERR, "⚠ skipped {$file} — it could not be read: {$failure->getMessage()}\n");

            return [];
        }
    }

    /**
     * The parsed components — what a scribe rewrites.
     *
     * @return list<Sfc>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Every FILE in this codebase — its components and the `.ts` modules beside them — as something
     * a rule can judge by NAME. The SAME query the backend's `whereFile()` opens
     * ({@see \JesseGall\CodeCommandments\Files\FileQuery}): a path is not a language, so the
     * machinery that judges one is not written twice.
     */
    public function whereFile(): FileQuery
    {
        $paths = array_map(static fn (Sfc $component): string => $component->path, $this->components);

        foreach ($this->standaloneTypes as $type) {
            $paths[] = $type->file;
        }

        return new FileQuery(array_values(array_unique($paths)));
    }

    /**
     * Every real element (text and the fragment root excluded).
     */
    public function whereElement(): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isElement());
    }

    /**
     * Every static TEXT node — the words a user actually reads, with no `{{ … }}` in them. Copy is
     * where a project's vocabulary shows up outside its identifiers (a caption reading "Taken" over
     * what is an input), and a rule could see only the code around it (#441). Interpolated text is
     * excluded: what it renders is decided elsewhere, so there is nothing here to read.
     */
    public function whereText(): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isStaticText());
    }

    /**
     * Elements of one of the given tags.
     */
    public function whereTag(string ...$tags): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isElement() && in_array($element->tag, $tags, true));
    }

    /**
     * Open a pattern over every element, checked by your own predicate.
     *
     * @param  Closure(ElementMatch): bool  $check
     */
    public function where(Closure $check): Query
    {
        return $this->whereElement()->where($check);
    }

    /**
     * Every TypeScript object type declared across the codebase — in a component's
     * `<script>` block or a standalone `.ts` file. The declaration-space selector, the
     * sibling of {@see whereElement}: it opens a {@see TypeQuery} the same way.
     */
    public function whereTypeDeclaration(): TypeQuery
    {
        return new TypeQuery(fn () => $this->typeDeclarations(), static fn (TypeDeclaration $declaration) => true);
    }

    /**
     * Every declared type across the codebase — the standalone `.ts` types plus each
     * component's `<script>`-block types — flattened once and cached.
     *
     * @return list<TypeDeclaration>
     */
    public function typeDeclarations(): array
    {
        return $this->typeDeclarations ??= $this->collectTypeDeclarations();
    }

    /**
     * @return list<TypeDeclaration>
     */
    private function collectTypeDeclarations(): array
    {
        $declarations = $this->standaloneTypes;

        foreach ($this->components as $component) {
            foreach ($component->blocks as $block) {
                if ($block->tag === 'script') {
                    $declarations = [...$declarations, ...TypeDeclaration::fromScript(new Script($block->content), $component->path, $component->source, $block->start)];
                }
            }
        }

        return $declarations;
    }

    /**
     * Every [element, component] pair across all templates — flattened once, cached.
     *
     * @return list<array{0: Element, 1: Sfc}>
     */
    public function nodes(): array
    {
        return $this->nodes ??= $this->flatten();
    }

    /**
     * @return list<array{0: Element, 1: Sfc}>
     */
    private function flatten(): array
    {
        $nodes = [];

        foreach ($this->components as $component) {
            self::collect($component->template, $component, $nodes);
        }

        return $nodes;
    }

    /**
     * @param  list<array{0: Element, 1: Sfc}>  $nodes
     */
    private static function collect(Element $node, Sfc $component, array &$nodes): void
    {
        foreach ($node->children as $child) {
            $nodes[] = [$child, $component];
            self::collect($child, $component, $nodes);
        }
    }

    /**
     * @return iterable<string>
     */
    private static function filesIn(string $path, string $extension, ExcludedPaths $excluded = new ExcludedPaths()): iterable
    {
        if (is_file($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === $extension) {
                yield $path;
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        $pruned = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file) use ($excluded): bool {
            if (! $file->isDir()) {
                return true;
            }

            return ! $file->isLink()
                && ! str_starts_with($file->getFilename(), '.')
                && ! in_array($file->getFilename(), ['vendor', 'node_modules'], true)
                && ! $excluded->covers($file->getPathname());
        });

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                yield $file->getPathname();
            }
        }
    }
}
