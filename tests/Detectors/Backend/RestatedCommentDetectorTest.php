<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RestatedCommentDetector;
use PHPUnit\Framework\TestCase;

/**
 * An inline comment earns its line by saying something the code does not. One that only spells the
 * next statement back in prose is pure tax — and that is decidable without understanding English:
 * EVERY content word of the comment is already a word of the code it annotates.
 */
final class RestatedCommentDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new RestatedCommentDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_a_comment_that_names_the_call_below_it(): void
    {
        $code = <<<'PHP'
        <?php
        class Checkout
        {
            public function place(Order $order): void
            {
                // save the order
                $this->orders->save($order);
            }
        }
        PHP;

        $this->assertSame(['Checkout::place'], $this->scopes($code));
    }

    public function test_flags_a_comment_that_restates_a_return_in_prose(): void
    {
        $code = <<<'PHP'
        <?php
        class Cart
        {
            public function total(): int
            {
                // returns the total price
                return $this->totalPrice;
            }
        }
        PHP;

        $this->assertSame(['Cart::total'], $this->scopes($code));
    }

    public function test_flags_a_comment_that_reads_a_condition_back_as_a_sentence(): void
    {
        $code = <<<'PHP'
        <?php
        class Gate
        {
            public function admit(Visitor $visitor): bool
            {
                // if the visitor is banned
                if ($visitor->isBanned()) {
                    return false;
                }

                return true;
            }
        }
        PHP;

        $this->assertSame(['Gate::admit'], $this->scopes($code));
    }

    public function test_leaves_a_comment_that_explains_a_why(): void
    {
        $code = <<<'PHP'
        <?php
        class Charges
        {
            public function retry(Charge $charge): void
            {
                // the gateway rejects a second charge inside its own idempotency window
                $this->charges->save($charge);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_comment_stating_an_invariant_in_the_code_s_own_words(): void
    {
        // Every word here IS a code word except the negation — and the negation is the whole point.
        $code = <<<'PHP'
        <?php
        class Ledger
        {
            public function post(Entry $entry): void
            {
                // the entry is never posted twice
                $this->entries->post($entry);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_docblock_alone(): void
    {
        // Docblocks are wanted, and their own sins (bloat, ceremony) have their own detectors.
        $code = <<<'PHP'
        <?php
        class Cart
        {
            /**
             * The total price.
             */
            public function totalPrice(): int
            {
                return 0;
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_does_not_read_words_out_of_a_nested_body(): void
    {
        // The comment annotates the `foreach` HEAD; words from the loop's body must not make an
        // informative comment look like a restatement.
        $code = <<<'PHP'
        <?php
        class Settlement
        {
            public function settle(array $orders): void
            {
                // oldest first, so a partial refund never outruns its charge
                foreach ($orders as $order) {
                    $this->refund($order);
                    $this->charge($order);
                }
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_section_divider_over_a_run_of_declarations(): void
    {
        // A heading is SUPPOSED to echo what it groups — the skill blesses structural dividers, and
        // real code is full of them (`// ----------[ OAuth client ]----------`).
        $code = <<<'PHP'
        <?php
        enum Permission: string
        {
            // Barcode Aliases
            case BARCODE_ALIASES_INDEX = 'barcode-aliases.index';
            case BARCODE_ALIASES_DELETE = 'barcode-aliases.delete';
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_divider_over_a_method_declaration(): void
    {
        $code = <<<'PHP'
        <?php
        class Settings
        {
            // ----------[ App Secrets ]----------

            public function appSecret(): string
            {
                return '';
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_lone_word_alone(): void
    {
        // One word is a label, not a narration — too thin to call redundant.
        $code = <<<'PHP'
        <?php
        class Cache
        {
            public function flush(): void
            {
                // flush
                $this->store->flush();
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
