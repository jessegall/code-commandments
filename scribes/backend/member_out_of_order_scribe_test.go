package backend

import (
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var memberOutOfOrder = scribeCase{rules.MemberOutOfOrderDetector{}, MemberOutOfOrderScribe{}}

func TestMemberOutOfOrderSortsTheHeadIntoTheCanonicalOrder(t *testing.T) {
	source := `<?php

class Invoice
{
    private array $lines = [];

    public string $reference {
        get => strtoupper($this->number);
    }

    public static int $issued = 0;

    /** Cents, always. */
    private const int PRECISION = 2;

    public string $number = 'inv-1';

    public function issue(): void
    {
    }
}`

	want := `<?php

class Invoice
{
    /** Cents, always. */
    private const int PRECISION = 2;

    public static int $issued = 0;

    public string $number = 'inv-1';

    private array $lines = [];

    public string $reference {
        get => strtoupper($this->number);
    }

    public function issue(): void
    {
    }
}`

	if got := memberOutOfOrder.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberOutOfOrderKeepsATightGroupTightAndASpacedOneSpaced(t *testing.T) {
	source := `<?php

class Wire
{
    private string $socket = '';
    private int $retries = 0;

    public const string VERSION = '2';

    public function open(): void
    {
    }
}`

	want := `<?php

class Wire
{
    public const string VERSION = '2';

    private string $socket = '';
    private int $retries = 0;

    public function open(): void
    {
    }
}`

	if got := memberOutOfOrder.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberOutOfOrderLeavesAHeadThatIsAlreadyCanonicalUntouched(t *testing.T) {
	source := `<?php

class Ledger
{
    private const int PRECISION = 2;

    public string $currency = 'EUR';

    private array $entries = [];

    public function post(): void
    {
    }
}`

	if memberOutOfOrder.rewrote(t, source) {
		t.Fatal("expected false")
	}
	if got := memberOutOfOrder.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestMemberOutOfOrderATrailingCommentStaysOnTheDeclarationItAnnotates(t *testing.T) {
	source := `<?php

class Order
{
    public string $status; // on_hold|completed

    public const string SOURCE = 'api';

    public string | null $channel; // main|api

    public function place(): void
    {
    }
}`

	want := `<?php

class Order
{
    public const string SOURCE = 'api';

    public string $status; // on_hold|completed

    public string | null $channel; // main|api

    public function place(): void
    {
    }
}`

	if got := memberOutOfOrder.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestMemberOutOfOrderMovesOnlyWhatIsMisplacedAndIsIdempotent(t *testing.T) {
	source := `<?php

class Roster
{
    public const string ROLE = 'driver';

    private array $names = [];

    protected int $shift = 0;

    public function add(): void
    {
    }
}`

	want := `<?php

class Roster
{
    public const string ROLE = 'driver';

    protected int $shift = 0;

    private array $names = [];

    public function add(): void
    {
    }
}`

	fixed := memberOutOfOrder.fixStable(t, source)

	if got := fixed; got != want {
		t.Fatalf("%s", got)
	}
	if found := memberOutOfOrder.findings(t, fixed); len(found) != 0 {
		t.Fatal(`the sin no longer fires`)
	}
	if got := memberOutOfOrder.fix(t, fixed); got != fixed {
		t.Fatalf("%s\n%s", `a second pass changes nothing`, got)
	}
}
