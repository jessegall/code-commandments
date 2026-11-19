# Flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### duplicated-branch-tail — in PHP

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```php
----------[ Bad ]----------

public function refund(int $orderId, int $cents): void
{
    if ($cents <= 5000) {
        $this->log[] = "card {$cents}";
        $this->note($orderId);
    } else {
        $this->log[] = "credit {$cents}";
        $this->note($orderId);
    }
}

----------[ Good ]----------

public function refundOnce(int $orderId, int $cents): void
{
    $this->log[] = ($cents <= 5000 ? 'card ' : 'credit ') . $cents;
    $this->note($orderId);
}
```

### duplicated-branch-tail — in TypeScript

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```ts
----------[ Bad ]----------

export function label(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
        parcel.zone = Math.max(parcel.zone, 1)
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
        parcel.zone = Math.max(parcel.zone, 1)
    } else {
        parcel.zone = Math.max(parcel.zone, 1)
    }

    return parcel
}

----------[ Good ]----------

export function labelOnce(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
    }
    parcel.zone = Math.max(parcel.zone, 1)

    return parcel
}
```

### duplicated-branch-tail — in Python

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```py
----------[ Bad ]----------

def restock(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    if item.opened:
        shelf.seconds += 1
        log.append(item.reason)
    else:
        shelf.fresh += 1
        log.append(item.reason)

----------[ Good ]----------

def restock_once(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    if item.opened:
        shelf.seconds += 1
    else:
        shelf.fresh += 1
    log.append(item.reason)
```

### duplicated-branch-tail — in C#

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```cs
----------[ Bad ]----------

public void Restock(ReturnedParcel parcel)
{
    if (parcel.Opened)
    {
        Seconds++;
        Log.Add(parcel.Reason);
    }
    else
    {
        Fresh++;
        Log.Add(parcel.Reason);
    }
}

----------[ Good ]----------

public void RestockOnce(ReturnedParcel parcel)
{
    if (parcel.Opened)
    {
        Seconds++;
    }
    else
    {
        Fresh++;
    }
    Log.Add(parcel.Reason);
}
```

### duplicated-branch-tail — in Go

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```go
----------[ Bad ]----------

// Label names a parcel heavy or light on its last label.
func Label(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
		parcel.Zone = normalZone(parcel.Zone)
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
		parcel.Zone = normalZone(parcel.Zone)
	} else {
		parcel.Zone = normalZone(parcel.Zone)
	}

	return parcel
}

----------[ Good ]----------

// LabelOnce labels by weight and settles the zone once, after the branches.
func LabelOnce(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
	}
	parcel.Zone = normalZone(parcel.Zone)

	return parcel
}
```

### loop-inverted-guard — in PHP

A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition

```php
----------[ Bad ]----------

public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total > 0) {
            $this->normalise($row);
            $this->persist($row);
        }
    }
}

----------[ Good ]----------

public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total <= 0) {
            continue;
        }

        $this->normalise($row);
        $this->persist($row);
    }
}
```

### loop-inverted-guard — in TypeScript

A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition

```ts
----------[ Bad ]----------

export function settle(members: Member[]): number {
    let settled = 0
    for (let index = 0; index < members.length; index++) {
        if (members[index].points < 0) {
            members[index].points = 0
            settled++
        }
    }

    return settled
}

----------[ Good ]----------

export function settleNegative(members: Member[]): number {
    let settled = 0
    for (const member of members) {
        if (member.points >= 0) {
            continue
        }
        member.points = 0
        settled++
    }

    return settled
}
```

### loop-inverted-guard — in Python

A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition

```py
----------[ Bad ]----------

def invoice_all(orders, ledger) -> int:
    sent = 0
    for order in orders:
        if order.delivered:
            invoice = ledger.draft(order.number, order.total())
            ledger.send(invoice)
            sent += 1
    return sent

----------[ Good ]----------

def invoice_delivered(orders, ledger) -> int:
    sent = 0
    for order in orders:
        if not order.delivered:
            continue
        invoice = ledger.draft(order.number, order.total())
        ledger.send(invoice)
        sent += 1
    return sent
```

### loop-inverted-guard — in C#

A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition

```cs
----------[ Bad ]----------

public long Bill(Order order, List<string> journal)
{
    long billed = 0;

    foreach (var line in order.Lines)
    {
        if (line.Quantity > 0)
        {
            billed += line.Subtotal.Cents;
            journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
        }
    }

    return billed;
}

----------[ Good ]----------

public long BillFlat(Order order, List<string> journal)
{
    long billed = 0;

    foreach (var line in order.Lines)
    {
        if (line.Quantity <= 0)
        {
            continue;
        }

        billed += line.Subtotal.Cents;
        journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
    }

    return billed;
}
```

### loop-inverted-guard — in Go

A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition

```go
----------[ Bad ]----------

// Post adds every entry that stands for money to the day's totals.
func (l *Ledger) Post(entries []Entry) {
	for _, entry := range entries {
		if !entry.Void && entry.Cents != 0 {
			l.totals[entry.Account] += entry.Cents
			l.posted++
		}
	}
}

----------[ Good ]----------

// PostFlat posts the same entries with the work at the loop's own level: the loop says what it skips in its
// first line, and everything after it is the posting.
func (l *Ledger) PostFlat(entries []Entry) {
	for _, entry := range entries {
		if entry.Void || entry.Cents == 0 {
			continue
		}
		l.totals[entry.Account] += entry.Cents
		l.posted++
	}
}
```

### nested-ternary — in PHP

A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression

```php
----------[ Bad ]----------

private function band(int $score): string
{
    return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C');
}

----------[ Good ]----------

// The same decision as a `match (true)` — each band reads on its own line, no
// precedence trap.

private function bandMatched(int $score): string
{
    return match (true) {
        $score >= 90 => 'A',
        $score >= 75 => 'B',
        default => 'C',
    };
}
```

### nested-ternary — in TypeScript

A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression

```ts
----------[ Bad ]----------

export function shelfFor(item: ParcelReturn): string {
    return item.opened ? (item.cents > 5000 ? 'inspection' : 'seconds') : 'new'
}

----------[ Good ]----------

export function shelfOf(item: ParcelReturn): string {
    if (!item.opened) {
        return 'new'
    }

    return item.cents > 5000 ? 'inspection' : 'seconds'
}
```

### nested-ternary — in Python

A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression

```py
----------[ Bad ]----------

def badge(order) -> str:
    return "green" if order.status == "sent" else "amber" if order.status == "draft" else "red"

----------[ Good ]----------

def badge_for(order) -> str:
    match order.status:
        case "sent":
            return "green"
        case "draft":
            return "amber"
        case _:
            return "red"
```

### nested-ternary — in C#

A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression

```cs
----------[ Bad ]----------

public static string Of(int grams) => grams < 500 ? "small" : grams < 5000 ? "medium" : "large";

----------[ Good ]----------

public static string Classed(int grams) => grams switch
{
    < 500 => "small",
    < 5000 => "medium",
    _ => "large",
};
```

### redundant-else — in PHP

An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing

```php
----------[ Bad ]----------

public function inStock(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        } else {
            $available[] = $product;
        }
    }

    return $available;
}

----------[ Good ]----------

// The guard handles the absent case and `continue`s; the happy path runs
// unindented with no redundant `else`.

public function available(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        }

        $available[] = $product;
    }

    return $available;
}
```

### redundant-else — in TypeScript

An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing

```ts
----------[ Bad ]----------

export function zoneName(parcel: Parcel): string {
    if (parcel.zone > 4) {
        throw new Error('no such zone')
    } else {
        return `zone ${parcel.zone}`
    }
}

----------[ Good ]----------

export function zoneLabel(parcel: Parcel): string {
    if (parcel.zone > 4) {
        throw new Error('no such zone')
    }

    return `zone ${parcel.zone}`
}
```

### redundant-else — in Python

An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing

```py
----------[ Bad ]----------

def price(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity
    else:
        subtotal = line.unit_price * line.quantity
        return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal

----------[ Good ]----------

def price_of(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity

    subtotal = line.unit_price * line.quantity
    return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal
```

### redundant-else — in C#

An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing

```cs
----------[ Bad ]----------

public Money Charge(Order order, string currency)
{
    if (order.Lines.Count == 0)
    {
        throw EmptyOrder.For(order);
    }
    else
    {
        var total = order.Total(currency);

        return total with { Cents = total.Cents + 250 };
    }
}

----------[ Good ]----------

public Money ChargeFlat(Order order, string currency)
{
    if (order.Lines.Count == 0)
    {
        throw EmptyOrder.For(order);
    }

    var total = order.Total(currency);

    return total with { Cents = total.Cents + 250 };
}
```

### redundant-else — in Go

An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing

```go
----------[ Bad ]----------

// Available is what may still be sold of an item.
func (w *Warehouse) Available(sku string) int {
	if w.closed {
		return 0
	} else {
		return w.onHand[sku] - w.promised[sku]
	}
}

----------[ Good ]----------

// AvailableTold is the same answer with the else gone: the return above it already says the rest runs only
// when the warehouse is open, so what was inside stands at the method's own level.
func (w *Warehouse) AvailableTold(sku string) int {
	if w.closed {
		return 0
	}

	return w.onHand[sku] - w.promised[sku]
}
```

### short-circuit-statement — in PHP

A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise

```php
----------[ Bad ]----------

public function send(string $address, bool $subscribed): void
{
    $subscribed && $this->mailer->send($address, 'Your weekly digest', $this->digest());
}

----------[ Good ]----------

public function sendGuarded(string $address, bool $subscribed): void
{
    if (! $subscribed) {
        return;
    }

    $this->mailer->send($address, 'Your weekly digest', $this->digest());
}
```

### short-circuit-statement — in TypeScript

A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise

```ts
----------[ Bad ]----------

export function relabel(parcel: Parcel): void {
    parcel.labels.length > 0 || parcel.labels.push('unlabelled')
}

----------[ Good ]----------

export function relabelEmpty(parcel: Parcel): void {
    if (parcel.labels.length === 0) {
        parcel.labels.push('unlabelled')
    }
}
```

### short-circuit-statement — in Python

A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise

```py
----------[ Bad ]----------

def ensure(self, key: str) -> None:
    self.store.has(key) or self.store.warm(key)
    self.store.touch(key)

----------[ Good ]----------

def ensure_warm(self, key: str) -> None:
    if not self.store.has(key):
        self.store.warm(key)
    self.store.touch(key)
```

### subject-ladder — in TypeScript

An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder

```ts
----------[ Bad ]----------

export function refundShare(item: ParcelReturn): number {
    if (item.reason === 'damaged') {
        return 100
    } else if (item.reason === 'late') {
        return 80
    } else if (item.reason === 'wrong-size') {
        return 60
    } else if (item.reason === 'changed-mind') {
        return 40
    }

    return 0
}

----------[ Good ]----------

export function refundShareByReason(item: ParcelReturn): number {
    return shares[item.reason] ?? 0
}
```

### subject-ladder — in Python

An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder

```py
----------[ Bad ]----------

def carrier_for(service: str) -> str:
    if service == "express":
        return "dhl"
    elif service == "standard":
        return "postnl"
    elif service == "economy":
        return "dpd"
    elif service == "freight":
        return "schenker"
    raise ValueError(service)

----------[ Good ]----------

class Service(Enum):
    EXPRESS = "express"
    STANDARD = "standard"
    ECONOMY = "economy"
    FREIGHT = "freight"

    def carrier(self) -> str:
        return {
            Service.EXPRESS: "dhl",
            Service.STANDARD: "postnl",
            Service.ECONOMY: "dpd",
            Service.FREIGHT: "schenker",
        }[self]
```

### subject-ladder — in C#

An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder

```cs
----------[ Bad ]----------

public static string Colour(OrderStatus status)
{
    if (status == OrderStatus.Placed)
    {
        return "grey";
    }
    else if (status == OrderStatus.Paid)
    {
        return "blue";
    }
    else if (status == OrderStatus.Shipped)
    {
        return "orange";
    }
    else if (status == OrderStatus.Delivered)
    {
        return "green";
    }

    return "red";
}

----------[ Good ]----------

public static string ColourOf(OrderStatus status) => status switch
{
    OrderStatus.Placed => "grey",
    OrderStatus.Paid => "blue",
    OrderStatus.Shipped => "orange",
    OrderStatus.Delivered => "green",
    OrderStatus.Cancelled => "red",
    _ => throw new ArgumentOutOfRangeException(nameof(status)),
};
```

### subject-ladder — in Go

An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder

```go
----------[ Bad ]----------

// RefundShare says how much of the price a return earns back, rung by rung on the reason.
func RefundShare(item Return) int {
	if item.Reason == "damaged" {
		return 100
	} else if item.Reason == "late" {
		return 80
	} else if item.Reason == "wrong-size" {
		return 60
	} else if item.Reason == "changed-mind" {
		return 40
	}

	return 0
}

----------[ Good ]----------

// RefundShareBySwitch answers the refund share with one switch over the reason.
func RefundShareBySwitch(item Return) int {
	switch item.Reason {
	case "damaged":
		return 100
	case "late":
		return 80
	case "wrong-size":
		return 60
	case "changed-mind":
		return 40
	}

	return 0
}
```

### ternary-statement — in PHP

A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value

```php
----------[ Bad ]----------

public function collapsed(string $id, array $below): array
{
    $gone = [];

    foreach ($below[$id] as $child) {
        $this->isOpen($child->id)
            ? array_push($gone, ...$this->collapsed($child->id, $below))
            : $gone[] = $child->id;
    }

    return $gone;
}

----------[ Good ]----------

public function collapsedBranched(string $id, array $below): array
{
    $gone = [];

    foreach ($below[$id] as $child) {
        if ($this->isOpen($child->id)) {
            array_push($gone, ...$this->collapsedBranched($child->id, $below));
        } else {
            $gone[] = $child->id;
        }
    }

    return $gone;
}
```

### ternary-statement — in TypeScript

A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value

```ts
----------[ Bad ]----------

export function stamp(parcel: Parcel): void {
    parcel.grams > 2000 ? parcel.labels.push('heavy') : parcel.labels.splice(0)
}

----------[ Good ]----------

export function stampOnce(parcel: Parcel): void {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    } else {
        parcel.labels.splice(0)
    }
}
```

### ternary-statement — in Python

A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value

```py
----------[ Bad ]----------

def back_up(source: Path, target: Path) -> None:
    shutil.copytree(source, target) if source.is_dir() else shutil.copy2(source, target)

----------[ Good ]----------

def back_up_path(source: Path, target: Path) -> None:
    if source.is_dir():
        shutil.copytree(source, target)
        return
    shutil.copy2(source, target)
```

### unreachable-statement — in PHP

A statement after a return, throw, break or continue in the same block: it can never run.

```php
----------[ Bad ]----------

public function body(int $orders): string
{
    if ($orders === 0) {
        return 'No orders today.';
        error_log('empty digest');
    }

    return str_repeat('*', min($orders, 20));
}

----------[ Good ]----------

public function summary(int $orders): string
{
    if ($orders === 0) {
        return 'No orders today.';
    }

    return $orders === 1 ? 'one order' : "{$orders} orders";
}
```

### unreachable-statement — in TypeScript

A statement after a return, throw, break or continue in the same block: it can never run.

```ts
----------[ Bad ]----------

export function expire(member: Member, lapsed: boolean): Member {
    if (!lapsed) {
        return member
    }
    member.points = 0
    return member
    member.tier = Tier.Bronze
}

----------[ Good ]----------

export function expireLapsed(member: Member, lapsed: boolean): Member {
    if (!lapsed) {
        return member
    }
    member.points = 0

    return member
}
```

### unreachable-statement — in Python

A statement after a return, throw, break or continue in the same block: it can never run.

```py
----------[ Bad ]----------

def expire(member: Member, lapsed: bool) -> Member:
    if not lapsed:
        return member
    member.points = 0
    return member
    member.tier = "bronze"

----------[ Good ]----------

def expire_lapsed(member: Member, lapsed: bool) -> Member:
    if not lapsed:
        return member
    member.points = 0

    return member
```

### unreachable-statement — in C#

A statement after a return, throw, break or continue in the same block: it can never run.

```cs
----------[ Bad ]----------

public void Expire(bool lapsed)
{
    if (!lapsed)
    {
        return;
    }
    Points = 0;
    return;
    Tier = "bronze";
}

----------[ Good ]----------

public void ExpireLapsed(bool lapsed)
{
    if (!lapsed)
    {
        return;
    }
    Points = 0;
}
```

### unreachable-statement — in Go

A statement after a return, throw, break or continue in the same block: it can never run.

```go
----------[ Bad ]----------

// Expire clears a member's points once they lapse.
func Expire(member Member, lapsed bool) Member {
	if !lapsed {
		return member
	}
	member.Points = 0
	panic("expiry is not run by hand")
	member.Tier = Bronze

	return member
}

----------[ Good ]----------

// ExpireLapsed clears a lapsed member's points with nothing written after it leaves.
func ExpireLapsed(member Member, lapsed bool) Member {
	if !lapsed {
		return member
	}
	member.Points = 0

	return member
}
```
