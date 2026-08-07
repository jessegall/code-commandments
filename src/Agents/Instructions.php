<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

use JesseGall\CodeCommandments\Support\GeneratedBlock;
use JesseGall\CodeCommandments\Support\MalformedBlock;
use JesseGall\CodeCommandments\Support\File;

/**
 * An instructions file a project owns — its `AGENTS.md`, its `CLAUDE.md` — into which we place ONE
 * managed block. The file is theirs; we are only ever a guest in it, so every rule here exists to
 * make sure a run of `composer update` cannot cost them a line they wrote.
 */
final class Instructions
{
    public function __construct(
        private readonly string $path,
        private readonly string $root,
    ) {}

    /**
     * Put $body in the $name block. True when the file now carries it.
     *
     * Where the block goes when there isn't one yet is the whole question. Inserting near the top
     * reads better and is wrong: a file can open with YAML front-matter (an instructions file
     * shared with a tool that requires it), or a fenced code block, and landing inside either
     * breaks the file for every reader — silently, because a block inside a fence is just text. So
     * a new block is APPENDED. The end of a document is the one position that cannot be inside
     * something else.
     */
    public function inject(string $name, string $body): bool
    {
        if (! $this->isInsideTheProject()) {
            fwrite(STDERR, "⚠ {$this->path} resolves outside this project — left alone.\n");

            return false;
        }

        $block = GeneratedBlock::begin($name, 'composer update') . "\n" . trim($body) . "\n" . GeneratedBlock::end($name);

        // No file yet is its own case, answered first. Treating it as an empty document would ask
        // every step below to reason about a document nobody wrote.
        if (! is_file($this->path)) {
            return $this->save(null, '# ' . basename($this->path, '.md') . "\n\n{$block}\n");
        }

        $original = (string) file_get_contents($this->path);
        $document = $this->withoutBom($original);
        $eol = $this->endOfLine($document);

        try {
            $updated = GeneratedBlock::replace($this->normalised($document), $name, "\n" . trim($body) . "\n");
        } catch (MalformedBlock $malformed) {
            fwrite(STDERR, "⚠ {$this->path}: {$malformed->getMessage()}\n");

            return false;
        }

        $updated ??= rtrim($this->normalised($document), "\n") . "\n\n{$block}\n";

        return $this->save($original, $this->restore($updated, $eol, $original));
    }

    /**
     * Is $other the very SAME file — a symlink or a hard link, not a second document? Compared by
     * inode, because the paths cannot answer it: two names for one file have different `realpath`s
     * when they are hard-linked, and on a case-insensitive filesystem `CLAUDE.md` and `AGENTS.md`
     * can resolve to one file whose spellings still differ. Both must exist to be the same thing,
     * which also settles the fresh-project case a path comparison gets exactly backwards — two
     * files that are merely both absent are not one file.
     */
    public function isSameFileAs(self $other): bool
    {
        $mine = @stat($this->path);
        $theirs = @stat($other->path);

        return $mine !== false && $theirs !== false
            && $mine['dev'] === $theirs['dev']
            && $mine['ino'] === $theirs['ino'];
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Refuse anything that resolves out of the project. An instructions file is often a symlink
     * into a dotfiles repository shared by every project the user owns — writing there would have
     * each `composer update` overwrite the last project's block with this one's.
     */
    private function isInsideTheProject(): bool
    {
        $root = realpath($this->root);
        $resolved = realpath($this->path) ?: realpath(dirname($this->path));

        return $root !== false && $resolved !== false && ($resolved === $root || str_starts_with($resolved, $root . '/'));
    }

    private function save(?string $original, string $updated): bool
    {
        if ($original === $updated) {
            return true;
        }

        if (! File::write($this->path, $updated)) {
            fwrite(STDERR, "⚠ {$this->path} could not be written — left as it was.\n");

            return false;
        }

        return true;
    }

    /**
     * A byte-order mark opens some instructions files and makes the frontmatter unreadable to a
     * strict parser if it ends up anywhere else — so it is taken off the front for the work and put
     * back exactly where it was.
     */
    private function withoutBom(string $document): string
    {
        return str_starts_with($document, "\xEF\xBB\xBF") ? substr($document, 3) : $document;
    }

    /**
     * The line ending the document already uses. A block written with the other kind gives the file
     * mixed endings, which under `core.autocrlf` churns the whole file on every single sync.
     */
    private function endOfLine(string $document): string
    {
        return str_contains($document, "\r\n") ? "\r\n" : "\n";
    }

    private function normalised(string $document): string
    {
        return str_replace("\r\n", "\n", $document);
    }

    private function restore(string $document, string $eol, ?string $original): string
    {
        $document = $eol === "\n" ? $document : str_replace("\n", $eol, $document);

        return $original !== null && str_starts_with($original, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" . $document : $document;
    }
}
