> Document little, and only what the code cannot say: what a thing is for, and why a surprising line is the way it is.
> Read this the moment you are about to write a doc comment, an inline comment or a type annotation that repeats
> what is already there, and when a documentation finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**A comment says what the code cannot. Everything else is the code's job.**

A comment is not checked by anything. The code changes, the comment stays, and from then on it is a false statement
that readers trust more than the code beside it. So every comment must earn its upkeep:

- **A doc comment is one or two lines** (three at most), in the present tense, about the code as it is now: what
  this type or function is for.
- **An inline comment is rare**, and only ever explains a non-obvious *why*: a constraint, a workaround, a reason
  the obvious code would be wrong.
- **Never the what.** If a comment repeats the line below it, delete it. If the line needs explaining, rename
  things until it does not.
- **Never the past.** "Previously", "used to", "now we", "refactored to": history belongs in the commit message,
  where it is attached to the change it describes.

```pseudo
// Bad
/**
 * OrderTotals class.
 *
 * This class was introduced when we moved away from the old Calculator. It previously lived in
 * the Cart, but now handles totals for orders too. Note that it does not handle refunds.
 *
 * @param lines  The lines
 * @return Money The money
 */
function total(lines: Line[]): Money {
    // loop over the lines
    for (line in lines) { … }
}

// Good
/** The amount a customer pays for a set of lines, tax included. */
function total(lines: Line[]): Money {
    for (line in lines) { … }
}
```

## The sins

### restated-comment

A comment that repeats the words of the statement it sits on. It adds nothing today and will be wrong tomorrow.
Delete it, or say the reason instead.

```pseudo
// Bad
// increment the retry count
retries = retries + 1

// Good: nothing, or the why
retries = retries + 1

// The gateway counts the first attempt as a retry, so we start at one.
retries = 1
```

### archaeology-comment

A comment that narrates how the code came to be. The reader wants to know what the code does now; the story of the
change belongs to the change record.

```pseudo
// Bad
// Previously this used the legacy mailer, but we switched to the queue in v2 after the outage.
queue.push(mail)

// Good
queue.push(mail)
```

### negative-space-comment

A comment that defends the code against an assumption the reader had no reason to make: what it does *not* do, what
it is *not* responsible for. Keep a comment only when it explains a real constraint.

```pseudo
// Bad
// Note: this does not send an email, and it does not touch the inventory.
function markPaid(order) { order.status = Status.Paid }

// Good
function markPaid(order) { order.status = Status.Paid }
```

### ceremony-docblock

A doc comment that repeats the signature: a tag per parameter saying its name and type again, a `@return` that
restates the return type. Remove the tags that add nothing and keep any sentence that actually explains.

```pseudo
// Bad
/**
 * Find a customer.
 *
 * @param id    int       The id
 * @return      Customer  The customer
 */
function find(id: int): Customer

// Good
/** The customer with this id; fails with CustomerNotFound when there is none. */
function find(id: int): Customer
```

### bloated-docblock

A type whose description runs to several paragraphs. Nobody maintains an essay. Say in a line or two what the type
is for, and move the supporting detail to where it is needed: the method it concerns, or a document.

```pseudo
// Bad
/**
 * The Scheduler.
 *
 * The scheduler is responsible for … (four paragraphs on the retry policy, the queue layout,
 * the history of the cron format and three usage examples)
 */
class Scheduler

// Good
/** Runs each due job once and reschedules it by its own interval. */
class Scheduler
```

### stacked-docblock

Several doc comments stacked on one declaration. Only one of them is attached to it; the others are noise, usually
left by a merge. Merge the useful text into one.

```pseudo
// Bad
/** Sends the invoice. */
/** @deprecated use deliver() */
/** Send an invoice to the customer by mail. */
function send(invoice)

// Good
/** Sends the invoice to its customer by mail. */
function send(invoice)
```

### dangling-doc-reference

A doc reference (`@see`, `{@link …}`, a cross-reference) that points at a declaration the project does not have. It
was renamed or deleted, and the comment was not. Correct the target or remove the reference.

```pseudo
// Bad
/** @see LegacyTaxTable.rateFor */        // ← LegacyTaxTable no longer exists
function rate(country)

// Good
/** @see TaxRates.for */
function rate(country)
```

### redundant-arrow-return-type

A one-expression function that annotates the return type its expression already has. It is the type-level form of
a restated comment: the reader sees the same fact twice. Let the expression supply the type.

```pseudo
// Bad
names = users.map((user): string => user.name)   // ← user.name already is a string

// Good
names = users.map(user => user.name)
```

### inline-docblock — in PHP

A docblock whose delimiter shares a line with its text: a one-liner, or a block that opens or closes next to
content. Give the delimiters their own lines.

```php
// Bad
/** The amount a customer pays, tax included. */
public function total(): Money

// Good
/**
 * The amount a customer pays, tax included.
 */
public function total(): Money
```

## In your language

- **PHP.** A docblock is multi-line, one to three lines of text; `@param`/`@return` only when they say more than
  the native types (a generic, an array shape).
- **Python.** A docstring is a line or two; `Args:`/`Returns:` sections that repeat the annotations are ceremony.
- **C#.** `/// <summary>` in one or two lines; no `<param>` that repeats the parameter's name.
- **TypeScript.** JSDoc types are redundant beside TypeScript's own: keep the sentence, drop `@param {string}`.
- **Go.** A doc comment starts with the name it documents and is a sentence, not a history.
