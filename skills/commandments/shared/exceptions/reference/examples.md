# Exceptions — fail loud, named, with the cause — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### generic-exception — in PHP

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

// in Shop\Shipping\TrackingFormatter
public function carrierNameNamed(Shipment $shipment): string
{
    return $shipment->carrier?->displayName()
        ?? throw CarrierMissing::for($shipment->id);
}

// Where the message went. The failure is named once, so every throw site says WHAT went wrong
// instead of spelling out a sentence the next site has to spell differently.

final class CarrierMissing extends RuntimeException
{
    public static function for(int|string $shipment): self
    {
        return new self("Shipment {$shipment} has no carrier.");
    }
}
```

### generic-exception — in TypeScript

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

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

export function postageInZone(zone: string, rates: Record<string, number>): number {
    if (!(zone in rates)) {
        throw new PostageUnknown(zone)
    }

    return rates[zone]
}
```

### generic-exception — in Vue

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```vue
----------[ Bad ]----------

async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}
```

### generic-exception — in Python

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```py
----------[ Bad ]----------

def basket(context):
    if context.session is None:
        raise RuntimeError("There is no active session in this context.")
    return context.session.basket

----------[ Good ]----------

# in sessions.py
class NoActiveSession(LookupError):
    @classmethod
    def reading(cls, what: str) -> "NoActiveSession":
        return cls(f"there is no active session to read the {what} from")

# in sessions.py
def current_basket(context):
    if context.session is None:
        raise NoActiveSession.reading("basket")
    return context.session.basket
```

### generic-exception — in C#

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```cs
----------[ Bad ]----------

public string Account(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw new Exception($"No carrier is registered as '{carrier}'.");
    }

    return account;
}

----------[ Good ]----------

// in CarrierDirectory.cs
public string AccountOf(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw UnknownCarrier.Named(carrier);
    }

    return account;
}

// in CarrierDirectory.cs
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string carrier) => new($"No carrier is registered as '{carrier}'.");
}
```

### message-at-throw — in PHP

A failure's message written as a string at the throw, not by a named constructor given the values involved

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

// in Shop\Shipping\TrackingFormatter
public function carrierNameOrFail(Shipment $shipment): string
{
    $carrier = $shipment->carrier ?? throw CarrierMissing::for($shipment->id);

    return $carrier->displayName();
}

// Where the message went. The failure is named once, so every throw site says WHAT went wrong
// instead of spelling out a sentence the next site has to spell differently.

final class CarrierMissing extends RuntimeException
{
    public static function for(int|string $shipment): self
    {
        return new self("Shipment {$shipment} has no carrier.");
    }
}
```

### message-at-throw — in TypeScript

A failure's message written as a string at the throw, not by a named constructor given the values involved

```ts
----------[ Bad ]----------

export function bonus(member: Member): number {
    if ('bronze' === member.tier) {
        return member.points
    } else if ('silver' === member.tier) {
        return member.points * 2
    } else if ('gold' === member.tier) {
        return member.points * 3
    } else if ('platinum' === member.tier) {
        return member.points * 5
    }
    throw new Error('a member holds no tier')
}

----------[ Good ]----------

export function spendableBalance(balance: number, memberId: string): number {
    if (balance < 0) {
        throw PointsLost.forMember(memberId)
    }

    return balance
}
```

### message-at-throw — in Python

A failure's message written as a string at the throw, not by a named constructor given the values involved

```py
----------[ Bad ]----------

def render(self, order, context, options) -> str:
    """Render the badge: each kind decides how."""
    raise NotImplementedError(f"{type(self).__name__} renders {order.number} in {context.name} as {options.mode} for {self.label}")

----------[ Good ]----------

def latest_ticket(payload: str) -> CounterTicket:
    title, sequence = json.loads(payload)
    if not title:
        raise ManifestUnreadable.missing_ticket()
    return CounterTicket(title, sequence)
```

### message-at-throw — in C#

A failure's message written as a string at the throw, not by a named constructor given the values involved

```cs
----------[ Bad ]----------

public static int NumberOf(string fileName)
{
    try
    {
        return int.Parse(Path.GetFileNameWithoutExtension(fileName));
    }
    catch (FormatException)
    {
        throw new ArgumentException($"{fileName} is not an invoice number.", nameof(fileName));
    }
}

----------[ Good ]----------

public static DispatchTicket Decode(string payload)
{
    return JsonSerializer.Deserialize<DispatchTicket>(payload) ?? throw new ManifestUnreadable();
}
```

### rethrown-unchanged — in PHP

A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does

```php
----------[ Bad ]----------

public function day(string $covered): string
{
    try {
        return (new \DateTimeImmutable($covered))->format('Y-m-d');
    } catch (\Exception $e) {
        throw $e;
    }
}

----------[ Good ]----------

public function dayCovered(string $covered): string
{
    return (new \DateTimeImmutable($covered))->format('Y-m-d');
}
```

### rethrown-unchanged — in TypeScript

A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does

```ts
----------[ Bad ]----------

export function stampedLabel(parcel: string, printer: { print(text: string): string; release(): void }): string {
    try {
        return printer.print(parcel)
    }
    catch (failure) {
        throw failure
    } finally {
        printer.release()
    }
}

----------[ Good ]----------

export function printedManifest(lines: string[], printer: { print(text: string): string; reset(): void }): string {
    try {
        return printer.print(lines.join('\n'))
    } catch (failure) {
        printer.reset()
        throw failure
    }
}
```

### rethrown-unchanged — in Python

A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does

```py
----------[ Bad ]----------

def hold(ledger, sku: str, quantity: int) -> None:
    try:
        ledger.hold(sku, quantity)
    except RuntimeError:
        raise

----------[ Good ]----------

def hold_stock(ledger, sku: str, quantity: int) -> None:
    ledger.hold(sku, quantity)
```

### rethrown-unchanged — in C#

A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does

```cs
----------[ Bad ]----------

public void Hold(string sku, int quantity)
{
    try
    {
        File.AppendAllText(Path.Combine(folder, "holds.csv"), $"{sku};{quantity}\n");
    }
    catch (IOException)
    {
        throw;
    }
}

----------[ Good ]----------

public void HoldStock(string sku, int quantity)
{
    File.AppendAllText(Path.Combine(folder, "holds.csv"), $"{sku};{quantity}\n");
}
```

### swallow-catch — in PHP

A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer

```php
----------[ Bad ]----------

public function forecast(string $city): array
{
    try {
        $body = $this->http->get("https://weather.test/{$city}");

        return (array) json_decode($body, true);
    } catch (\Throwable $e) {
        return [];
    }
}

----------[ Good ]----------

public function forecastOrThrow(string $city): array
{
    try {
        $body = $this->http->get("https://weather.test/{$city}");

        return (array) json_decode($body, true);
    } catch (\Throwable $e) {
        report($e);

        throw $e;
    }
}
```

### swallow-catch — in TypeScript

A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer

```ts
----------[ Bad ]----------

export function parsedReturnReason(raw: string): unknown {
    try {
        return JSON.parse(raw)
    }
    catch {
        return null
    }
}

----------[ Good ]----------

export function returnReason(raw: string): unknown {
    return JSON.parse(raw)
}
```

### swallow-catch — in Python

A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer

```py
----------[ Bad ]----------

def load_catalog(path: Path):
    try:
        return Catalog(json.loads(path.read_text()))
    except Exception:
        return {}

----------[ Good ]----------

# in catalog_file.py
@dataclass(frozen=True)
class Catalog:
    products: dict

# in catalog_file.py
class CatalogUnreadable(Exception):
    @classmethod
    def at(cls, path: Path) -> "CatalogUnreadable":
        return cls(f"the catalog at {path} is not valid JSON")

# in catalog_file.py
def read_catalog(path: Path) -> Catalog:
    try:
        return Catalog(json.loads(path.read_text()))
    except json.JSONDecodeError as error:
        raise CatalogUnreadable.at(path) from error
```

### swallow-catch — in C#

A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer

```cs
----------[ Bad ]----------

public Dictionary<string, int>? Load()
{
    try
    {
        return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json")));
    }
    catch (Exception)
    {
        return null;
    }
}

----------[ Good ]----------

public Dictionary<string, int> LoadOrEmpty()
{
    try
    {
        return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json"))) ?? [];
    }
    catch (FileNotFoundException)
    {
        return [];
    }
}
```

### swallow-catch — in Go

A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer

```go
----------[ Bad ]----------

func importBins(codes []string, read func(string) error) int {
	loaded := 0
	for _, code := range codes {
		if err := read(code); err != nil {
			continue
		}
		loaded++
	}

	return loaded
}

----------[ Good ]----------

func receiveAllBins(codes []string, read func(string) error) error {
	for _, code := range codes {
		if err := read(code); err != nil {
			return err
		}
	}

	return nil
}
```

### wrapping-without-cause — in PHP

A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost

```php
----------[ Bad ]----------

public function upload(string $path): void
{
    try {
        $this->pushToBucket($path);
    } catch (\Throwable $storageError) {
        throw new IntegrationException($path);
    }
}

----------[ Good ]----------

public function uploadChecked(string $path): void
{
    try {
        $this->pushToBucket($path);
    } catch (\Throwable $storageError) {
        throw new IntegrationException($path, previous: $storageError);
    }
}
```

### wrapping-without-cause — in TypeScript

A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost

```ts
----------[ Bad ]----------

export function refundParcel(parcelId: string, gateway: { refund(id: string): void }): void {
    try {
        gateway.refund(parcelId)
    } catch (error) {
        throw new RefundFailed(parcelId)
    }
}

----------[ Good ]----------

export function refundThroughGateway(parcelId: string, gateway: { refund(id: string): void }, attempts: number): void {
    for (let attempt = 1; attempt <= attempts; attempt++) {
        try {
            gateway.refund(parcelId)

            return
        } catch (error) {
            if (attempt === attempts) {
                throw new RefundFailed(parcelId, { cause: error })
            }
        }
    }
}
```

### wrapping-without-cause — in Python

A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost

```py
----------[ Bad ]----------

def read_feed(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError as timeout:
        client.close()
        raise FeedUnavailable.at(url)

----------[ Good ]----------

def read_feed_from(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError as timeout:
        client.close()
        raise FeedUnavailable.at(url) from timeout
```

### wrapping-without-cause — in C#

A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost

```cs
----------[ Bad ]----------

public static string Read(string path)
{
    try
    {
        return File.ReadAllText(path);
    }
    catch (IOException e)
    {
        throw new StockFileUnreadable($"Could not read the stock file: {e.Message}");
    }
}

----------[ Good ]----------

public static string ReadKeepingTheCause(string path)
{
    try
    {
        return File.ReadAllText(path);
    }
    catch (IOException e)
    {
        throw new StockFileUnreadable("Could not read the stock file.", e);
    }
}
```

### wrapping-without-cause — in Go

A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost

```go
----------[ Bad ]----------

func printForOrder(order string, print func() error) error {
	if err := print(); err != nil {
		return fmt.Errorf("order %s label: %v", order, err)
	}

	return nil
}

----------[ Good ]----------

func printWithCause(order string, print func() error) error {
	if err := print(); err != nil {
		return fmt.Errorf("order %s label: %w", order, err)
	}

	return nil
}
```
