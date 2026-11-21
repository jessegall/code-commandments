# Flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### deep-nesting — in PHP

A decision sits deep inside other decisions in one function

```php
----------[ Bad ]----------

public function resolve(Product $product, array $overrides, string $region): int
{
    if (array_key_exists($region, $overrides)) {
        if ($product->price_cents > 0) {
            if ($overrides[$region] < $product->price_cents) {
                return $overrides[$region];
            }
        }
    }

    return $product->price_cents;
}

----------[ Good ]----------

public function resolveFlat(Product $product, array $overrides, string $region): int
{
    if (! array_key_exists($region, $overrides)) {
        return $product->price_cents;
    }

    if ($product->price_cents <= 0) {
        return $product->price_cents;
    }

    return min($overrides[$region], $product->price_cents);
}
```

### deep-nesting — in TypeScript

A decision sits deep inside other decisions in one function

```ts
----------[ Bad ]----------

export function sortIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams > 0) {
            if (bins.has(parcel.zone)) {
                if (parcel.labels.length > 0) {
                    bins.get(parcel.zone)?.push(parcel)
                }
            }
        }
    }
}

----------[ Good ]----------

export function sortLabelledIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams <= 0 || parcel.labels.length === 0) {
            continue
        }
        bins.get(parcel.zone)?.push(parcel)
    }
}
```

### deep-nesting — in Python

A decision sits deep inside other decisions in one function

```py
----------[ Bad ]----------

def usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer == customer.id:
            if coupon.starts <= today <= coupon.ends:
                if coupon.uses_left > 0:
                    if not coupon.revoked:
                        return coupon
    return None

----------[ Good ]----------

def first_usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer != customer.id or coupon.revoked:
            continue
        if coupon.starts <= today <= coupon.ends and coupon.uses_left > 0:
            return coupon
    return None
```

### deep-nesting — in C#

A decision sits deep inside other decisions in one function

```cs
----------[ Bad ]----------

// Restocking walks every warehouse's shelves and decides, four choices deep, what to reorder — the
// decision that matters is buried under the ones that only filter.
public sealed class Restock(IReadOnlyDictionary<string, IReadOnlyList<Shelf>> warehouses)
{
    public IReadOnlyList<string> Reorders()
    {
        var reorders = new List<string>();

        foreach (var (warehouse, shelves) in warehouses)
        {
            foreach (var shelf in shelves)
            {
                if (shelf.OnHand < shelf.Minimum)
                {
                    if (shelf.Supplier is not null)
                    {
                        reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                    }
                }
            }
        }

        return reorders;
    }

    public IReadOnlyList<string> ReordersFlat() =>
        warehouses
            .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
            .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
            .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
            .ToList();

    private static string Reorder(string warehouse, Shelf shelf) =>
        $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
}

----------[ Good ]----------

// Restocking walks every warehouse's shelves and decides, four choices deep, what to reorder — the
// decision that matters is buried under the ones that only filter.
public sealed class Restock(IReadOnlyDictionary<string, IReadOnlyList<Shelf>> warehouses)
{
    public IReadOnlyList<string> Reorders()
    {
        var reorders = new List<string>();

        foreach (var (warehouse, shelves) in warehouses)
        {
            foreach (var shelf in shelves)
            {
                if (shelf.OnHand < shelf.Minimum)
                {
                    if (shelf.Supplier is not null)
                    {
                        reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                    }
                }
            }
        }

        return reorders;
    }

    public IReadOnlyList<string> ReordersFlat() =>
        warehouses
            .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
            .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
            .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
            .ToList();

    private static string Reorder(string warehouse, Shelf shelf) =>
        $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
}
```

### deep-nesting — in Go

A decision sits deep inside other decisions in one function

```go
----------[ Bad ]----------

// Dispatch sends out every crate the bay is holding.
func (b *Bay) Dispatch(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if !bay.closed {
			for _, crate := range bay.crates {
				if crate.Packed {
					sent++
				}
			}
		}
	}

	return sent
}

----------[ Good ]----------

// Sent is what Dispatch counts, with each level guarded away instead of nested.
func (b *Bay) Sent(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if bay.closed {
			continue
		}

		sent += bay.packed()
	}

	return sent
}
```

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

// ParcelReturns shelves and refunds the parcels customers send back.
public sealed class ParcelReturns
{
    public List<string> Log { get; } = new();

    public int Seconds { get; private set; }

    public int Fresh { get; private set; }

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

    public int Refund(int cents)
    {
        if (cents <= 0)
        {
            throw new NothingToRefund(cents);
            cents = 0;
        }

        return cents;
    }

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

    public string RefundNote(string reason)
    {
        reason = reason.Trim().ToLowerInvariant();
        return $"refund: {reason}";
    }
}

----------[ Good ]----------

// ParcelReturns shelves and refunds the parcels customers send back.
public sealed class ParcelReturns
{
    public List<string> Log { get; } = new();

    public int Seconds { get; private set; }

    public int Fresh { get; private set; }

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

    public int Refund(int cents)
    {
        if (cents <= 0)
        {
            throw new NothingToRefund(cents);
            cents = 0;
        }

        return cents;
    }

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

    public string RefundNote(string reason)
    {
        reason = reason.Trim().ToLowerInvariant();
        return $"refund: {reason}";
    }
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

// Invoicing only bills lines that were actually ordered — and says so by wrapping the whole loop body
// in that condition, so the billing sits a level deeper than the loop needs.
public sealed class Invoicing
{
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

    public string? FirstBackordered(Order order)
    {
        string? found = null;

        foreach (var line in order.Lines)
        {
            if (line.Quantity > 99)
            {
                found = line.Sku;
                break;
            }
        }

        return found;
    }
}

----------[ Good ]----------

// Invoicing only bills lines that were actually ordered — and says so by wrapping the whole loop body
// in that condition, so the billing sits a level deeper than the loop needs.
public sealed class Invoicing
{
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

    public string? FirstBackordered(Order order)
    {
        string? found = null;

        foreach (var line in order.Lines)
        {
            if (line.Quantity > 99)
            {
                found = line.Sku;
                break;
            }
        }

        return found;
    }
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

def quote(parcel, carrier) -> float:
    band = "heavy" if parcel.kg > 20 else "medium" if parcel.kg > 5 else "light"
    return carrier.price(band, parcel.kg)

----------[ Good ]----------

def quote_banded(parcel, carrier) -> float:
    band = next(name for floor, name in BANDS if parcel.kg > floor or floor == 0)
    return carrier.price(band, parcel.kg)
```

### nested-ternary — in C#

A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression

```cs
----------[ Bad ]----------

// The size class printed on a parcel label, by weight.
public static class ParcelSizes
{
    public static string Of(int grams) => grams < 500 ? "small" : grams < 5000 ? "medium" : "large";

    public static string Classed(int grams) => grams switch
    {
        < 500 => "small",
        < 5000 => "medium",
        _ => "large",
    };
}

----------[ Good ]----------

// The size class printed on a parcel label, by weight.
public static class ParcelSizes
{
    public static string Of(int grams) => grams < 500 ? "small" : grams < 5000 ? "medium" : "large";

    public static string Classed(int grams) => grams switch
    {
        < 500 => "small",
        < 5000 => "medium",
        _ => "large",
    };
}
```

### non-counting-for — in PHP

A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count

```php
----------[ Bad ]----------

public function nearest(object $widget): string
{
    for ($one = $widget; $one !== null; $one = $one->stacked ? $one->above : null) {
        if ($one->caption !== '') {
            return $one->caption;
        }
    }

    return 'untitled';
}

----------[ Good ]----------

public function nearestWhile(object $widget): string
{
    $one = $widget;

    while ($one !== null) {
        if ($one->caption !== '') {
            return $one->caption;
        }

        $one = $one->stacked ? $one->above : null;
    }

    return 'untitled';
}
```

### non-counting-for — in TypeScript

A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count

```ts
----------[ Bad ]----------

export function zonesHopped(start: number, hops: Map<number, number>): number[] {
    const zones: number[] = []
    for (let zone = start; hops.has(zone); zone = hops.get(zone) ?? 0) {
        zones.push(zone)
    }

    return zones
}

----------[ Good ]----------

export function everyOtherZone(last: number): number[] {
    const zones: number[] = []
    for (let zone = 1; zone <= last; zone += 2) {
        zones.push(zone)
    }

    return zones
}
```

### non-counting-for — in C#

A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count

```cs
----------[ Bad ]----------

public static class Approvals
{
    public static List<string> Approvers(Approval first)
    {
        var names = new List<string>();

        for (Approval? step = first; step != null; step = step.Next)
        {
            names.Add(step.Approver);
        }

        return names;
    }

    public static List<string> Signed(Approval first) => first.Chain().Select(step => step.Approver).ToList();
}

----------[ Good ]----------

// in Approvals.cs
// One sign-off in the chain an order passes before it ships.
public sealed class Approval(string approver, Approval? next)
{
    public string Approver { get; } = approver;

    public Approval? Next { get; } = next;

    public IEnumerable<Approval> Chain()
    {
        Approval? current = this;

        while (current != null)
        {
            yield return current;
            current = current.Next;
        }
    }
}

// in Approvals.cs
public static class Approvals
{
    public static List<string> Approvers(Approval first)
    {
        var names = new List<string>();

        for (Approval? step = first; step != null; step = step.Next)
        {
            names.Add(step.Approver);
        }

        return names;
    }

    public static List<string> Signed(Approval first) => first.Chain().Select(step => step.Approver).ToList();
}
```

### non-counting-for — in Go

A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count

```go
----------[ Bad ]----------

func zonesHopped(start int, hops map[int]int) []int {
	visited := make(map[int]bool, len(hops))
	order := make([]int, 0, len(hops))
	for zone := start; hops[zone] != 0; zone = hops[zone] {
		if visited[zone] {
			break
		}
		visited[zone] = true
		order = append(order, zone)
	}
	return order
}

----------[ Good ]----------

// everyOtherZone lists the odd zones up to the last one.
func everyOtherZone(last int) []int {
	var zones []int
	for zone := 1; zone <= last; zone += 2 {
		zones = append(zones, zone)
	}

	return zones
}
```

### query-in-loop — in Python

A database query runs on each iteration

```py
----------[ Bad ]----------

def archive_overdue(batches: dict[int, set[str]], cutoff: int, already_archived: set[str]) -> int:
    changed = 0
    for month, accounts in sorted(batches.items()):
        if month >= cutoff:
            break
        pending = accounts.difference(already_archived)
        if not pending:
            continue
        changed += StatementCutoff.objects.filter(account__in=pending).update(archived=True)
        already_archived.update(pending)
    return changed

----------[ Good ]----------

def archive_cutoffs(batches: dict[int, set[str]], cutoff: int, already_archived: set[str]) -> int:
    due = {account for month, accounts in batches.items() if month < cutoff for account in accounts}
    pending = due.difference(already_archived)
    changed = StatementCutoff.objects.filter(account__in=pending).update(archived=True)
    already_archived.update(pending)
    return changed
```

### query-in-loop — in C#

A database query runs on each iteration

```cs
----------[ Bad ]----------

public sealed class ParcelSnapshotReader(IServiceProvider source, Action<string> intake)
{
    private readonly IServiceProvider intakeServices = source;

    public bool PublishProjection(string gate)
    {
        if (gate.Length == 0) return false;
        var projection = this.intakeServices.GetRequiredService<ParcelSnapshotProjection>();
        intake(gate + ": " + projection.GetType().Name);
        return true;
    }

    public decimal ReadParcels(DbSet<ParcelSnapshotProjection> parcels, Queue<int> queue, decimal running)
    {
        var total = running;
        while (queue.TryDequeue(out var next))
        {
            var label = parcels.Find(next);
            total += label.Length;
            intake(label);
        }
        return total;
    }

    public string ReadOne(DbSet<ParcelSnapshotProjection> rows, int identifier)
    {
        return rows.Find(identifier);
    }

    public string ProjectionName(ParcelSnapshotProjection projection)
    {
        return projection.GetType().Name;
    }
}

----------[ Good ]----------

public sealed class ParcelSnapshotReader(IServiceProvider source, Action<string> intake)
{
    private readonly IServiceProvider intakeServices = source;

    public bool PublishProjection(string gate)
    {
        if (gate.Length == 0) return false;
        var projection = this.intakeServices.GetRequiredService<ParcelSnapshotProjection>();
        intake(gate + ": " + projection.GetType().Name);
        return true;
    }

    public decimal ReadParcels(DbSet<ParcelSnapshotProjection> parcels, Queue<int> queue, decimal running)
    {
        var total = running;
        while (queue.TryDequeue(out var next))
        {
            var label = parcels.Find(next);
            total += label.Length;
            intake(label);
        }
        return total;
    }

    public string ReadOne(DbSet<ParcelSnapshotProjection> rows, int identifier)
    {
        return rows.Find(identifier);
    }

    public string ProjectionName(ParcelSnapshotProjection projection)
    {
        return projection.GetType().Name;
    }
}
```

### reassigned-parameter — in PHP

A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.

```php
----------[ Bad ]----------

public function record(string $sku, int $quantity): void
{
    $sku = strtoupper(trim($sku));
    $this->counted[$sku] = $quantity;
}

----------[ Good ]----------

public function recordScanned(string $scanned, int $quantity): void
{
    $sku = strtoupper(trim($scanned));
    $this->counted[$sku] = $quantity;
}
```

### reassigned-parameter — in TypeScript

A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.

```ts
----------[ Bad ]----------

export function postageCents(grams: number, zone: number): number {
    grams = Math.max(grams, 100)

    return grams * zone
}

----------[ Good ]----------

export function billedPostageCents(grams: number, zone: number): number {
    const billed = Math.max(grams, 100)

    return billed * zone
}
```

### reassigned-parameter — in Python

A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.

```py
----------[ Bad ]----------

def postage_cents(grams: int, zone: int) -> int:
    grams = max(grams, 100)
    return grams * zone

----------[ Good ]----------

def billed_postage_cents(grams: int, zone: int) -> int:
    billed = max(grams, 100)
    return billed * zone
```

### reassigned-parameter — in C#

A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.

```cs
----------[ Bad ]----------

// ParcelPostage labels parcels by weight and counts what a belt can take.
public sealed class ParcelPostage
{
    public List<string> Labels { get; } = new();

    public int Zone { get; private set; }

    public void Label(int grams)
    {
        if (grams > 2000)
        {
            Labels.Add("heavy");
            Zone = Math.Max(Zone, 1);
        }
        else if (grams > 500)
        {
            Labels.Add("medium");
            Zone = Math.Max(Zone, 1);
        }
        else
        {
            Zone = Math.Max(Zone, 1);
        }
    }

    public int Sorted(IEnumerable<int> weights)
    {
        var taken = 0;
        foreach (var grams in weights)
        {
            if (grams > 30000)
            {
                continue;
                taken--;
            }
            taken++;
        }

        return taken;
    }

    public void Weigh(int grams)
    {
        if (grams > 2000)
        {
            Labels.Add("heavy");
        }
        Zone = Math.Max(Zone, 1);
    }

    public int PostageCents(int grams, int zone)
    {
        grams = Math.Max(grams, 100);
        return grams * zone;
    }

    public int BilledPostageCents(int grams, int zone)
    {
        var billed = Math.Max(grams, 100);
        return billed * zone;
    }

    public bool TryWeigh(string label, out int grams)
    {
        grams = label.Length * 100;
        return grams > 0;
    }
}

----------[ Good ]----------

// ParcelPostage labels parcels by weight and counts what a belt can take.
public sealed class ParcelPostage
{
    public List<string> Labels { get; } = new();

    public int Zone { get; private set; }

    public void Label(int grams)
    {
        if (grams > 2000)
        {
            Labels.Add("heavy");
            Zone = Math.Max(Zone, 1);
        }
        else if (grams > 500)
        {
            Labels.Add("medium");
            Zone = Math.Max(Zone, 1);
        }
        else
        {
            Zone = Math.Max(Zone, 1);
        }
    }

    public int Sorted(IEnumerable<int> weights)
    {
        var taken = 0;
        foreach (var grams in weights)
        {
            if (grams > 30000)
            {
                continue;
                taken--;
            }
            taken++;
        }

        return taken;
    }

    public void Weigh(int grams)
    {
        if (grams > 2000)
        {
            Labels.Add("heavy");
        }
        Zone = Math.Max(Zone, 1);
    }

    public int PostageCents(int grams, int zone)
    {
        grams = Math.Max(grams, 100);
        return grams * zone;
    }

    public int BilledPostageCents(int grams, int zone)
    {
        var billed = Math.Max(grams, 100);
        return billed * zone;
    }

    public bool TryWeigh(string label, out int grams)
    {
        grams = label.Length * 100;
        return grams > 0;
    }
}
```

### reassigned-parameter — in Go

A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.

```go
----------[ Bad ]----------

func postageCents(grams int, zone int) int {
	grams = max(grams, 100)

	return grams * zone
}

----------[ Good ]----------

// billedPostageCents bills a parcel at no less than a hundred grams.
func billedPostageCents(grams int, zone int) int {
	billed := max(grams, 100)

	return billed * zone
}
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

// Checkout refuses an empty order and then carries the whole checkout inside an `else` the `throw`
// already made unnecessary.
public sealed class Checkout
{
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

    public Money ChargeFlat(Order order, string currency)
    {
        if (order.Lines.Count == 0)
        {
            throw EmptyOrder.For(order);
        }

        var total = order.Total(currency);

        return total with { Cents = total.Cents + 250 };
    }

    public string Greeting(Order order, bool returning)
    {
        string text;

        if (returning)
        {
            text = $"Welcome back — order {order.Reference}";
        }
        else
        {
            text = $"Thanks for your first order, {order.Reference}";
        }

        return text;
    }
}

----------[ Good ]----------

// Checkout refuses an empty order and then carries the whole checkout inside an `else` the `throw`
// already made unnecessary.
public sealed class Checkout
{
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

    public Money ChargeFlat(Order order, string currency)
    {
        if (order.Lines.Count == 0)
        {
            throw EmptyOrder.For(order);
        }

        var total = order.Total(currency);

        return total with { Cents = total.Cents + 250 };
    }

    public string Greeting(Order order, bool returning)
    {
        string text;

        if (returning)
        {
            text = $"Welcome back — order {order.Reference}";
        }
        else
        {
            text = $"Thanks for your first order, {order.Reference}";
        }

        return text;
    }
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

def forget(node, registry) -> None:
    node.built and node.stale and registry.forget(node.name)

----------[ Good ]----------

def forget_stale(node, registry) -> None:
    if node.built and node.stale:
        registry.forget(node.name)
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

// A badge colour is picked by testing the status against one member after another — a dispatch
// written as a ladder, so a new status is a new rung someone has to remember to add.
public static class StatusBadge
{
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

    public static string ColourOf(OrderStatus status) => status switch
    {
        OrderStatus.Placed => "grey",
        OrderStatus.Paid => "blue",
        OrderStatus.Shipped => "orange",
        OrderStatus.Delivered => "green",
        OrderStatus.Cancelled => "red",
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}

----------[ Good ]----------

// A badge colour is picked by testing the status against one member after another — a dispatch
// written as a ladder, so a new status is a new rung someone has to remember to add.
public static class StatusBadge
{
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

    public static string ColourOf(OrderStatus status) => status switch
    {
        OrderStatus.Placed => "grey",
        OrderStatus.Paid => "blue",
        OrderStatus.Shipped => "orange",
        OrderStatus.Delivered => "green",
        OrderStatus.Cancelled => "red",
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}
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

def audit(log, verbose: bool, event: str) -> None:
    log.write(f"audit: {event}") if verbose else None
    log.flush()

----------[ Good ]----------

def audit_event(log, verbose: bool, event: str) -> None:
    if verbose:
        log.write(f"audit: {event}")
    log.flush()
```

### unnamed-condition — in PHP

A condition of three or more parts over two or more objects whose decision has no name

```php
----------[ Bad ]----------

public function dispatch(Crate $crate): void
{
    if ($crate->isPacked() && $crate->hasLabel() && $this->bay->isFree()) {
        $this->bay->take($crate);
        $this->sent[] = $crate->reference;
    }
}

----------[ Good ]----------

// in Shop\Dispatch\BayDispatcher
public function dispatchTold(Crate $crate): void
{
    if ($crate->readyFor($this->bay)) {
        $this->bay->take($crate);
        $this->sent[] = $crate->reference;
    }
}

// in Shop\Dispatch\Crate
public function readyFor(LoadingBay $bay): bool
{
    return $this->packed && $this->labelled && $bay->isFree();
}
```

### unnamed-condition — in TypeScript

A condition of three or more parts over two or more objects whose decision has no name

```ts
----------[ Bad ]----------

export function choosePickup(order: { packed: boolean }, window: { open: boolean; minutes: number }): string {
    let message = 'collect tomorrow'
    if (order.packed && window.open && window.minutes > 15) {
        message = 'collect today'
    }

    return message
}

----------[ Good ]----------

export function pickupMessage(order: { packed: boolean }, window: { open: boolean; minutes: number }): string {
    const canCollectToday = order.packed && window.open && window.minutes > 15

    return canCollectToday ? 'collect today' : 'collect tomorrow'
}
```

### unnamed-condition — in Python

A condition of three or more parts over two or more objects whose decision has no name

```py
----------[ Bad ]----------

def dispatch(self, crate: Crate) -> None:
    if crate.packed and crate.labelled and self.bay.is_free():
        self.bay.take(crate)
        self.sent.append(crate.reference)

----------[ Good ]----------

# in bay_loading.py
def ready_for(self, bay: "LoadingBay") -> bool:
    return self.packed and self.labelled and bay.is_free()

# in bay_loading.py
def dispatch_told(self, crate: Crate) -> None:
    if crate.ready_for(self.bay):
        self.bay.take(crate)
        self.sent.append(crate.reference)
```

### unnamed-condition — in C#

A condition of three or more parts over two or more objects whose decision has no name

```cs
----------[ Bad ]----------

public sealed class BayDispatcher(LoadingBay bay)
{
    private readonly List<string> sent = [];

    public void Dispatch(Crate crate)
    {
        if (crate.Packed && crate.Labelled && bay.IsFree())
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    public void DispatchTold(Crate crate)
    {
        if (crate.ReadyFor(bay))
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    public IReadOnlyList<string> Sent() => sent;
}

----------[ Good ]----------

// in BayLoading.cs
// Loading a crate out of a bay. The condition asks the crate two things and the bay a third, and a reader has
// to hold all three in mind to see that it means "ready to load". Below it, the FIX: the crate answers that
// question under its own name, and each object answers for its own part of it.
public sealed class Crate(string reference, bool packed, bool labelled)
{
    public string Reference { get; } = reference;

    public bool Packed { get; } = packed;

    public bool Labelled { get; } = labelled;

    public bool ReadyFor(LoadingBay bay) => Packed && Labelled && bay.IsFree();
}

// in BayLoading.cs
public sealed class BayDispatcher(LoadingBay bay)
{
    private readonly List<string> sent = [];

    public void Dispatch(Crate crate)
    {
        if (crate.Packed && crate.Labelled && bay.IsFree())
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    public void DispatchTold(Crate crate)
    {
        if (crate.ReadyFor(bay))
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    public IReadOnlyList<string> Sent() => sent;
}
```

### unnamed-condition — in Go

A condition of three or more parts over two or more objects whose decision has no name

```go
----------[ Bad ]----------

func archiveWhenReady(label *Parcel, printer *LabelPrinter, archive func()) {
	if label.Grams > 0 && printer.Ready && printer.Paper > 0 {
		archive()
	}
}

----------[ Good ]----------

func archiveWhenPrintable(label *Parcel, printer *LabelPrinter, archive func()) {
	canArchive := label.Grams > 0 && printer.Ready && printer.Paper > 0
	if canArchive {
		archive()
	}
}
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

// LoyaltyPoints spends and expires the points a member collects.
public sealed class LoyaltyPoints
{
    public int Points { get; private set; }

    public string Tier { get; private set; } = "bronze";

    public string Redeem(int cost)
    {
        string result;
        if (Points < cost)
        {
            Console.WriteLine(Points);
            result = "spent";
        }
        else
        {
            Points -= cost;
            result = "spent";
        }

        return result;
    }

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

    public void ExpireLapsed(bool lapsed)
    {
        if (!lapsed)
        {
            return;
        }
        Points = 0;
    }

    public string Render()
    {
        return Describe(Tier);

        static string Describe(string tier) => tier.ToUpperInvariant();
    }

    public int PointsFor(IEnumerable<int> spent, int bonus)
    {
        foreach (var amount in spent)
        {
            bonus += amount / 10;
        }
        return bonus;
    }
}

----------[ Good ]----------

// LoyaltyPoints spends and expires the points a member collects.
public sealed class LoyaltyPoints
{
    public int Points { get; private set; }

    public string Tier { get; private set; } = "bronze";

    public string Redeem(int cost)
    {
        string result;
        if (Points < cost)
        {
            Console.WriteLine(Points);
            result = "spent";
        }
        else
        {
            Points -= cost;
            result = "spent";
        }

        return result;
    }

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

    public void ExpireLapsed(bool lapsed)
    {
        if (!lapsed)
        {
            return;
        }
        Points = 0;
    }

    public string Render()
    {
        return Describe(Tier);

        static string Describe(string tier) => tier.ToUpperInvariant();
    }

    public int PointsFor(IEnumerable<int> spent, int bonus)
    {
        foreach (var amount in spent)
        {
            bonus += amount / 10;
        }
        return bonus;
    }
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
