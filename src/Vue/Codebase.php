<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

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
    /** @var list<array{0: Element, 1: Sfc}>|null */
    private ?array $nodes = null;

    /**
     * @param  list<Sfc>  $components
     */
    private function __construct(private readonly array $components) {}

    public static function fromString(string $vue, string $path = 'component.vue'): self
    {
        return new self([Sfc::parse($vue, $path)]);
    }

    /**
     * Parse every `.vue` file under the given root(s).
     *
     * @param  string|list<string>  $path
     */
    public static function scan(string|array $path): self
    {
        $files = [];

        foreach ((array) $path as $root) {
            foreach (self::vueFilesIn($root) as $file) {
                $files[$file] = true;
            }
        }

        $components = [];

        foreach (array_keys($files) as $file) {
            $source = @file_get_contents($file);

            if ($source !== false) {
                $components[] = Sfc::parse($source, $file);
            }
        }

        return new self($components);
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
     * Every real element (text and the fragment root excluded).
     */
    public function whereElement(): Query
    {
        return new Query(fn (): array => $this->nodes(), static fn (Element $element): bool => $element->isElement());
    }

    /**
     * Elements of one of the given tags.
     */
    public function whereTag(string ...$tags): Query
    {
        return new Query(fn (): array => $this->nodes(), static fn (Element $element): bool => $element->isElement() && in_array($element->tag, $tags, true));
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
    private static function vueFilesIn(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        $pruned = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file): bool {
            if (! $file->isDir()) {
                return true;
            }

            return ! $file->isLink()
                && ! str_starts_with($file->getFilename(), '.')
                && ! in_array($file->getFilename(), ['vendor', 'node_modules'], true);
        });

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                yield $file->getPathname();
            }
        }
    }
}
