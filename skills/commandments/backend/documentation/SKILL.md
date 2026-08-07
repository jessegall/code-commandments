---
name: commandments-backend-documentation
description: "How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description."
---

# Documentation — concise, present-tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
> is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
> inline comment at all.

## The principle

A docblock and a comment are not free: every line a reader must scan is a tax on understanding, and a
line that restates the code, or narrates how it got here, is pure tax with no return. The bar is high —
write a doc only when it tells the reader something the code itself does not.

Docs are still **wanted**, not banned. A short docblock on a class or method is good and expected: one
sentence saying what it *is* or *does*, plus the `@param` / `@return` / `@throws` type contract. Keep it to
a line or two, present-tense, about the code as it is now — never *how* it works internally, *why* it
changed, or what it *used to* be. Git holds the history; when you replace code you replace it, you don't
annotate the grave.

Inline comments are the rarest of all — default to none. The code already says *what* it does; the only
comment worth writing explains a non-obvious **why** the code can't: a hidden invariant, a workaround for an
external bug, a constraint the reader can't infer. (A structural section divider in a large class is fine
if the codebase already uses them — structural, not narrative.) Everything else: don't write it.

## Rules

- Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.
- Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.
- A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)
- Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.
  _expand it — `repent` does this for you_
- State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.
- An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.
- One declaration carries ONE docblock — merge a stack into a single block, because the language hands only the last one to a reader's tooling.
  _merge them into one block — `repent` does this for you_

## Bad → good

### archaeology-comment

History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted")

```php
----------[ Bad ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handle(array $event): void
{
    // formerly lived inline in the StripeController; was extracted here
    $type = $event['type'];

    $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
}

----------[ Good ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handleRefund(array $event): void
{
    // a refund carries no id of its own, so the charge reference identifies the row
    $this->record('refund', $this->reference($event));
}
```

### bloated-docblock

Multi-paragraph class docblock (class too big)

```php
----------[ Bad ]----------

/**
 * This class is responsible for importing legacy orders from the old system.
 * It was originally extracted from the monolith during the 2023 migration and
 * has been refactored several times since. It reads the legacy CSV export, maps
 * each row to a customer, and creates the order. Previously this logic lived in
 * the OrderController but was moved here to keep controllers thin.
 *
 * TODO: remove once the legacy importer is fully decommissioned.
 */
final class LegacyOrderImporter
{
    // previously this returned an array, now it returns a Customer or null
    public function findCustomer(string $email): ?Customer
    {
        // loop over all customers and find the matching one
        return Customer::query()->where('email', $email)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $customer = $this->findCustomer($row['email'] ?? '');

            // changed from update() to direct assignment in v2
            if ($customer !== null) {
                $customer->imported = true;
                $customer->save();
            }
        }
    }

    /**
     * The FIX: a row with no email is an absence at the SOURCE, so the boundary names the failure and
     * throws. The `?? ''` version looked up "the customer whose email is the empty string" and carried
     * that fake all the way to the import — the throw stops the row here, where the truth is known.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importStrictly(array $rows): void
    {
        foreach ($rows as $row) {
            $email = $row['email'] ?? throw new MissingImportEmail();

            $this->findCustomer($email)?->markImported();
        }
    }

    public function emailKnown(string $email): bool
    {
        return $this->findCustomer($email)?->exists ?? false;
    }
}

----------[ Good ]----------

/**
 * Renders the monthly sales spreadsheet finance imports by hand.
 */
final class MonthlySalesReport
{
    public function __construct(private readonly SalesGrouping $grouping) {}

    public function render(int $month): string
    {
        return $this->grouping->for($month) . "-report.xlsx";
    }
}
```

### ceremony-docblock

Docblock that only restates the typed signature (`@param Type $x`, no description)

```php
----------[ Bad ]----------

/**
 * @param  int  $sequence
 * @return  string
 */
public function format(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}

----------[ Good ]----------

/**
 * Renders the number finance quotes on a remittance — the sequence is zero-padded to six digits
 * so invoices sort lexically in the ledger export.
 */
public function formatForRemittance(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}
```

### dangling-doc-reference

A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is

```php
----------[ Bad ]----------

/**
 * Picks the cheapest carrier. Rates come from {@see \Shop\Shipping\RemovedRateBook} — deleted, so the
 * link is stale.
 */
public function cheapest(string $zone, float $weight): string
{
    return $weight > 10.0 ? "freight:{$zone}" : "parcel:{$zone}";
}

----------[ Good ]----------

/**
 * Picks the cheapest carrier for a heavy parcel. Rates come from
 * {@see \Shop\Shipping\ShippingRateRegistry} — the class the rate book became, so the reference
 * resolves.
 */
public function cheapestFreight(string $zone): string
{
    return "freight:{$zone}";
}
```

### inline-docblock

A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content

```php
----------[ Bad ]----------

/** A node in a test log tree — it holds its own children, so a failure is knowledge it could answer. */
final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /**
     * @var list<LogLine>
     */
    public array $children = [];

    /**
     * A bool about the line itself, named as a claim instead of a question.
     */
    public function reports(): bool
    {
        return $this->level === 'error';
    }

    /**
     * The FIX is the NAME: same body, same class, asked as a question. `if ($line->isErrored())`
     * reads as English at the call site, where `if ($line->reports())` reads as a claim.
     */
    public function isErrored(): bool
    {
        return $this->level === 'error';
    }
}

----------[ Good ]----------

/**
 * A node in a test log tree — it holds its own children, so a failure is knowledge it could answer.
 */
final class LogEntry
{
    public string $level = 'info';

    /**
     * @var list<LogEntry>
     */
    public array $children = [];

    public function isError(): bool
    {
        return $this->level === 'error';
    }
}
```

### negative-space-comment

A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code")

```php
----------[ Bad ]----------

public function get(string $code): SkuEntry
{
    // no magic here; a missing code yields an empty entry
    return new SkuEntry();
}

----------[ Good ]----------

/**
 * The affirmative form of the same note: it states what the registry DOES, so a reader never has
 * to be talked out of a suspicion the code never raised.
 */
public function entry(string $code): SkuEntry
{
    // an unknown code answers with an empty entry, so a caller never tests for absence
    return new SkuEntry();
}
```

### restated-comment

An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`)

```php
----------[ Bad ]----------

public function load(int $cartId): void
{
    // store the cart snapshot
    $this->snapshot = CartSnapshot::of($cartId);
}

----------[ Good ]----------

/**
 * The comment now carries what the code cannot: WHY the snapshot is taken once.
 */
public function open(int $cartId): void
{
    // the cart is frozen at checkout entry, so a coupon is weighed against the cart as it was then
    $this->snapshot = CartSnapshot::of($cartId);
}
```

### stacked-docblock

Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees

```php
----------[ Bad ]----------

/**
 * A plain on-disk report file — NOT an Eloquent model, even though it has a
 * save(). Mutating-then-saving one is just building a file, not the model-at-the-
 * call-site sin.
 */
final class ReportFile
{
    public string $name = '';

    public string $contents = '';

    private const string EXTENSION = '.csv';

    /**
     * Writes the report where the export job expects it.
     */
    /**
     * The second block PHP never hands to a reader.
     */
    public function save(): void
    {
        // write to disk
    }

    /**
     * Writes the report where the export job expects it — the second block folded in, so the one
     * block PHP hands a reader says everything both used to.
     */
    public function archive(): void
    {
        $this->save();
    }
}

----------[ Good ]----------

/**
 * Writes the report where the export job expects it — the second block folded in, so the one
 * block PHP hands a reader says everything both used to.
 */
public function archive(): void
{
    $this->save();
}
```

## When it fires

- History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted") — `ArchaeologyCommentDetector`
- Multi-paragraph class docblock (class too big) — `BloatedDocblockDetector`
- Docblock that only restates the typed signature (`@param Type $x`, no description) — `CeremonyDocblockDetector`
- A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is — `DanglingDocReferenceDetector`
- A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content — `InlineDocblockDetector`
- A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code") — `NegativeSpaceCommentDetector`
- An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`) — `RestatedCommentDetector`
- Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees — `StackedDocblockDetector`

## Checklist

- [ ] Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.
- [ ] Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- [ ] A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.
- [ ] A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)
- [ ] Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.
- [ ] State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.
- [ ] An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.
- [ ] One declaration carries ONE docblock — merge a stack into a single block, because the language hands only the last one to a reader's tooling.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
