package backend

import (
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var memberAfterMethod = scribeCase{rules.MemberAfterMethodDetector{}, MemberAfterMethodScribe{}}

func TestMemberAfterMethodHoistsAStrayPropertyAboveTheFirstMethod(t *testing.T) {
	source := `<?php

class Cart
{
    public function add(string $sku): void
    {
        $this->lines[] = $sku;
    }

    /** @var list<string> */
    private array $lines = [];
}`

	want := `<?php

class Cart
{
    /** @var list<string> */
    private array $lines = [];

    public function add(string $sku): void
    {
        $this->lines[] = $sku;
    }
}`

	if got := memberAfterMethod.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberAfterMethodCarriesTheDocblockAndLandsUnderTheStateAlreadyAtTheHead(t *testing.T) {
	source := `<?php

class Retry
{
    private int $attempts = 0;

    public function attempt(): int
    {
        return $this->attempts < self::MAX ? ++$this->attempts : self::MAX;
    }

    /** How many times the gateway tolerates the same idempotency key. */
    private const int MAX = 3;
}`

	want := `<?php

class Retry
{
    private int $attempts = 0;

    /** How many times the gateway tolerates the same idempotency key. */
    private const int MAX = 3;

    public function attempt(): int
    {
        return $this->attempts < self::MAX ? ++$this->attempts : self::MAX;
    }
}`

	if got := memberAfterMethod.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberAfterMethodARunSplitAroundAMethodKeepsItsOriginalOrder(t *testing.T) {
	source := `<?php

class Wire
{
    public const string FIRST = 'a';

    public function render(): string
    {
        return self::FIRST . self::SECOND . self::THIRD;
    }

    public const string SECOND = 'b';

    public const string THIRD = 'c';
}`

	want := `<?php

class Wire
{
    public const string FIRST = 'a';

    public const string SECOND = 'b';

    public const string THIRD = 'c';

    public function render(): string
    {
        return self::FIRST . self::SECOND . self::THIRD;
    }
}`

	if got := memberAfterMethod.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberAfterMethodDoesNotTouchAClassThatAlreadyStatesFirst(t *testing.T) {
	source := `<?php

class Ledger
{
    private const int PRECISION = 2;

    private array $entries = [];

    public function __construct(private readonly Clock $clock) {}

    public function post(int $cents): void
    {
        $this->entries[] = $cents;
    }
}`

	if memberAfterMethod.rewrote(t, source) {
		t.Fatal("expected false")
	}
	if got := memberAfterMethod.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestMemberAfterMethodIsIdempotent(t *testing.T) {
	source := `<?php

class Invoice
{
    public function issue(): void
    {
    }

    private string $slug = 'inv';
}`

	fixed := memberAfterMethod.fixStable(t, source)

	if found := memberAfterMethod.findings(t, fixed); len(found) != 0 {
		t.Fatal(`the sin no longer fires`)
	}
	if got := memberAfterMethod.fix(t, fixed); got != fixed {
		t.Fatalf("%s\n%s", `a second pass changes nothing`, got)
	}
}
