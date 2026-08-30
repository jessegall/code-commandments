<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Every measurement filed for this build, newest last. Kept apart from the {@see Board} because they have
 * different lifetimes: a hold is released and gone, while what a process said about a tree stays true
 * about that tree for ever.
 */
final class Receipts
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.receipts'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'What a tool READ from a process for each item — the command, the code it returned, and the '
                . 'tree it stood in. Only a run produces one, which is what makes the number a measurement '
                . 'rather than a claim.',
            [],
            list: 'one `item<TAB>command<TAB>exit<TAB>head<TAB>merge-base<TAB>time` per line. Two receipts '
                . 'of one item that measured different trees do not agree — the later supersedes.',
            safe: 'the measurements are forgotten; the work is not',
        );
    }

    public function file(Receipt $receipt): void
    {
        $state = $this->file->read();

        $this->file->write($state->withItems([...$state->items(), $receipt->toLine()]));
    }

    /**
     * The most recent receipt for $item, by WHEN IT WAS READ rather than where it sits in the file. A
     * measurement that starts first can land last, so file order would let a slow green settle on top of
     * a fresh red with nothing in the record to show it happened.
     *
     * @return Option<Receipt>
     */
    public function latestFor(string $item): Option
    {
        $latest = Option::none();

        foreach ($this->file->read()->items() as $line) {
            foreach (Receipt::fromLine($line) as $receipt) {
                if ($receipt->item === $item && $this->supersedes($receipt, $latest)) {
                    $latest = Option::some($receipt);
                }
            }
        }

        return $latest;
    }

    /**
     * Does $receipt stand over the one already held? Ties go to the later line, so two measurements read
     * in the same minute keep the order they were filed in.
     *
     * @param  Option<Receipt>  $held
     */
    private function supersedes(Receipt $receipt, Option $held): bool
    {
        foreach ($held as $standing) {
            return $receipt->at >= $standing->at;
        }

        return true;
    }
}
