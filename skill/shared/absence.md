> A value that might not be there is a decision, and it is made once: where the value is born.
> Read this before you write a nullable type, a `return null`, a `?? default`, a `?.`, or an "is it there?" check,
> and when an absence finding points here.
> The examples are pseudocode: `T?` is a type that may hold nothing, `??` is "or else". Read them as your language.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Decide absence where the value is born, and say the decision in the type.**

When something can be missing, there are four honest answers. Pick one at the source, and every reader downstream is
spared the question:

| The missing case means | Do this at the source | The caller then |
|---|---|---|
| a bug, or a broken promise: the thing must exist | throw a named exception | uses the value, no check |
| nothing matched | return an empty collection | loops over it, no check |
| an optional collaborator or callback | give a default that does nothing (a Null Object, a no-op) | calls it, no check |
| real, meaningful absence | say so in the type (`T?`, an `Option<T>`) | must handle both cases, once |

What is never honest is the fifth answer: a nullable type that every caller treats as present, or a fake value (`''`,
`0`, `[]`, `false`) that stands in for "missing" and looks like data. Both hide the decision instead of making it, and
the bug surfaces far from where it started.

```pseudo
// Bad: the finder does not decide, and every caller acts as if the value is always there
function find(id): Customer? {
    return customers.get(id)
}

name = find(id).name                 // ← crashes somewhere else, later
email = find(id)?.email ?? ""        // ← or invents a customer with no email

// Good: the finder decides, and the type carries the decision
function find(id): Customer {
    return customers.get(id) ?? throw CustomerNotFound.withId(id)
}

name = find(id).name
```

## The sins

### de-nulled-finder

A finder returns `T?`, and every caller takes the result as present. The type says "might be missing", the code says
"never is". Make the finder give a value or report the miss by name.

```pseudo
// Bad
function byBarcode(barcode): Product? {   // ← says the product may be missing
    return products.firstWhere(barcode)
}

name = byBarcode(code).name   // ← and every caller acts as if it never is
stock = byBarcode(code).stock

// Good
function byBarcode(barcode): Product {
    return products.firstWhere(barcode) ?? throw ProductNotFound.withBarcode(barcode)
}
```

### nullable-registry-lookup

A keyed store answers a miss with nothing. A registry is asked for keys that are expected to exist, so a miss is a
failure with a name, not a `null` the caller must remember to check.

```pseudo
// Bad
class TariffBook {
    function forZone(zone): Tariff? {
        return this.tariffs[zone] ?? null   // ← an unknown zone becomes a null the caller must remember to check
    }
}

// Good
class TariffBook {
    function forZone(zone): Tariff {
        return this.tariffs[zone] ?? throw UnknownZone.named(zone)   // ← a miss fails by name
    }

    function has(zone): bool {
        return zone in this.tariffs
    }
}
```

### phantom-nullable

A field is declared optional, and every read of it assumes it is there. The declaration lies. Make the type agree
with the values the field can hold: require it where the object is built.

```pseudo
// Bad
class Invoice {
    customer: Customer?   // ← declared optional

    function recipient() { return this.customer.name }   // ← read as if it is always there
    function address()   { return this.customer.address }
}

// Good
class Invoice {
    customer: Customer

    constructor(customer: Customer) { this.customer = customer }
}
```

### masked-invariant

A value the design requires is read through `?.` and given a literal fallback. If the invariant is ever broken, the
code answers `false` or `0` instead of failing, and the bug is invisible. Assert the invariant, or make the field
certain where it is set.

```pseudo
// Bad: "focus() ran first" is required, and a broken promise reads as "no"
function covers(date): bool {
    return this.period?.includes(date) ?? false   // ← a missing period answers "no" instead of failing
}

// Good
function covers(date): bool {
    if (this.period == null) {
        throw LedgerNotFocused.beforeCovers()
    }

    return this.period.includes(date)
}
```

### manufactured-fake-fill

A missing key is replaced by an empty scalar and then looked up anyway. The lookup is asked for a customer with
email `''`: a question nobody meant to ask. Resolve the missing key first, and only look up a key that exists.

```pseudo
// Bad
customer = customers.byEmail(row["email"] ?? "")   // ← asks for the customer whose email is empty

// Good
email = row["email"] ?? throw RowWithoutEmail.at(row)

customer = customers.byEmail(email)
```

### cancelled-coalesce

A fallback that is compared against the very value it falls back to. Missing and empty land in one branch, and the
code never says that it meant both.

```pseudo
// Bad
if ((name ?? "") != "") {   // ← falls back to "" only to compare with ""
    greet(name)
}

// Good: say both halves, or make the value always present where it is made
if (name != null && name != "") {
    greet(name)
}
```

### coalesced-loop-subject

A loop header that swaps a missing collection for an empty one. The absence is swallowed in the one place nobody
looks. Handle the missing input before the loop, or require a collection.

```pseudo
// Bad
for (parcel in manifest[carrier] ?? []) {   // ← a missing carrier quietly loops zero times
    queue(parcel)
}

// Good: the type that owns the data answers with a collection, always
for (parcel in manifest.parcelsFor(carrier)) {
    queue(parcel)
}
```

### conditional-array-spread

A spread whose "nothing" branch is an empty collection used as a placeholder. The reader has to decode a ternary
inside a literal to learn that a key is sometimes there. Build the collection, then add the conditional part as a
statement.

```pseudo
// Bad
query = { sort: sort, ...(brand == null ? {} : { brand: brand }) }
// ↑ an empty map is spread in, only to say "no brand"

// Good
query = { sort: sort }

if (brand != null) {
    query.brand = brand
}
```

### nullable-callback

A callback declared optional, checked for absence, then called. Every call site of the callback carries the check.
Default it to a named no-op, or require it.

```pseudo
// Bad
function run(work, onRetry: Callback? = null) {
    try { work() } catch (failure) {
        if (onRetry != null) {   // ← every call of the callback needs this check
            onRetry(failure)
        }
    }
}

// Good
function run(work, onRetry: Callback = NoOp) {   // ← a default that does nothing
    try { work() } catch (failure) {
        onRetry(failure)
    }
}
```

### capability-probe-hub

Several methods each ask the same loosely typed field whether it can do something before using it. The question
"what is this thing?" is asked again in every method. Decide what the object provides when it is handed over, and
type the field for it.

```pseudo
// Bad
class ExportPreferences {
    settings: object   // ← typed so loosely that every method must ask what it is

    function canEmail()   { return this.settings is ExportSettings && this.settings.email }
    function canArchive() { return this.settings is ExportSettings && this.settings.archive }
}

// Good: the owner receives the capability it needs, already decided
class ExportPreferences {
    settings: ExportSettings   // ← decided once, where it is handed over

    function canEmail()   { return this.settings.email }
    function canArchive() { return this.settings.archive }
}
```

### blank-string-default

A required text parameter defaults to `""`. A caller that forgets it gets a blank that travels on as if it were data.
Require the text, or give absence its own type.

```pseudo
// Bad
function invite(email: string, name: string = "") { … }   // ← forget the name and a blank travels on as data

// Good
function invite(email: string, name: string) { … }
```

### placeholder-filled-data

A data type built with `""` in a field it requires. The object looks complete and is not. Validate before
constructing, or model the incomplete state as a type of its own.

```pseudo
// Bad
draft = Customer(name: form.name, email: "", country: "")   // ← looks complete and is not

// Good
draft = CustomerDraft(name: form.name)

customer = draft.completedWith(email, country)
```

### raw-decoded-array-return

A function returns decoded data (JSON, a row, a payload) before anything checked it. Every caller then reaches into
an untyped bag and guesses which keys exist. Validate at the boundary and return a type the caller can rely on.

```pseudo
// Bad
function settings(): map {
    return json.decode(file.read("settings.json"))   // ← unchecked data leaves the boundary
}

limit = settings()["limit"] ?? 10   // ← so every caller guesses which keys exist

// Good
function settings(): Settings {
    return Settings.from(json.decode(file.read("settings.json")))
}

limit = settings().limit
```

### match-default-returns-null

A dispatch whose default arm quietly returns nothing. A value nobody expected turns into "no answer" instead of a
failure, and the caller cannot tell an unknown case from a real absence. Handle the cases you expect, and signal the
one you do not.

```pseudo
// Bad
function carrierFor(zone): Carrier? {
    return match (zone) {
        "eu"    => Carrier.Post
        "world" => Carrier.Air
        default => null   // ← an unknown zone becomes "no carrier"
    }
}

// Good
function carrierFor(zone): Carrier {
    return match (zone) {
        "eu"    => Carrier.Post
        "world" => Carrier.Air
        default => throw UnknownZone.named(zone)
    }
}
```

### false-for-nothing — in PHP

A return typed `X|false`, where `false` stands for "nothing" or "failed". Every caller must test for it, and one who
forgets treats `false` as data.

```php
// Bad
public function position(string $sku): int|false   // ← false stands for "not found"
{
    return array_search($sku, $this->skus, true);
}

// Good
public function position(string $sku): int
{
    $position = array_search($sku, $this->skus, true);

    return $position === false ? throw UnknownSku::named($sku) : $position;
}
```

### option-as-nullable — in PHP

An `Option<T>` treated as a nullable: `?Option`, `Option|null`, `unwrapOr(null)`. That is two layers of "maybe" where
one was the point. Wrap once at the seam, then consume it as an option.

```php
// Bad
public function coupon(): ?Option { … }   // ← a maybe inside a maybe

$code = $order->coupon()?->unwrapOr(null);

// Good
public function coupon(): Option
{
    return Option::fromNullable($this->couponCode);
}

$label = $order->coupon()->match(
    some: fn (string $code) => "Coupon {$code}",
    none: fn () => 'No coupon',
);
```

### erased-null-object — in PHP

A Null Object that renders blank, written into a `string` slot. The type coerces it straight back to `''`, and the
object that modelled the absence is gone.

```php
// Bad
public function __construct(public readonly string $nickname) {}

new Profile(nickname: (string) new NoNickname);   // ← the cast turns the object back into ''

// Good: the slot's type carries the object
public function __construct(public readonly Nickname $nickname) {}

new Profile(nickname: new NoNickname);
```

### blank-string-on-the-wire — in PHP

A string field sent to the frontend that the frontend reads as "absent" when it is blank. Two sides now share an
unwritten rule. Say absence in the type, so both sides spell it one way.

```php
// Bad
final class ProfileData extends Data
{
    public function __construct(public readonly string $avatarUrl = '') {}
    // ↑ '' means "no avatar", and only the frontend knows it
}

// Good
final class ProfileData extends Data
{
    public function __construct(public readonly ?string $avatarUrl = null) {}
}
```

### csharp-null-forgiven — in C#

The null-forgiving `!` on a value declared nullable. The compiler said it may be null, and `!` silences the compiler
instead of deciding.

```csharp
// Bad
var customer = customers.Find(id)!;   // ← silences the compiler and decides nothing

// Good
var customer = customers.Find(id) ?? throw CustomerNotFound.WithId(id);
```

### falsely-optional-field · defended-certain-field — in TypeScript

A field declared optional that is initialised where it is declared, and a `?.` on a field the class declares as
always present. Both doubt something the design already rules out, and every `?.` and `??` that follows defends a
case that cannot happen.

```ts
// Bad
class Cart {
    private lines?: Line[] = []   // ← optional, yet always set

    total(): number {
        return this.lines?.reduce((sum, line) => sum + line.price, 0) ?? 0
        // ↑ defends a case that cannot happen
    }
}

// Good
class Cart {
    private lines: Line[] = []

    total(): number {
        return this.lines.reduce((sum, line) => sum + line.price, 0)
    }
}
```

## In your language

- **PHP.** `?T` and `T|null` are the nullable types; `?->` and `??` are the defences to be suspicious of. An
  `Option` (php-types) is for absence a caller must handle; a collection method returns `[]`, never `null`.
- **Python.** `X | None` and `Optional[X]`; the defences are `x or default`, `getattr(x, "y", default)` and
  `.get(key, default)`. Raise a named exception instead of returning `None` for "not found".
- **C#.** Turn nullable reference types on and believe them: `T?` only where absence is real, never `!`.
- **TypeScript.** Pick one spelling of missing (`undefined` or `null`) per type, and no `?.` on a field that is
  always set.
- **Go.** Absence is the second return value: `value, ok` or `value, err`. Check it at once and leave.
