package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var redundantArrowReturnType = scribeCase{rules.RedundantArrowReturnTypeDetector{}, RedundantArrowReturnTypeScribe{}}

func TestRedundantArrowReturnTypeTakesTheTypeOffAndLeavesTheArrowIntact(t *testing.T) {
	source := `<?php

namespace App;

final class Panel
{
    private string $name = 'x';

    public function all(): array
    {
        return [
            fn (): string => $this->name,
        ];
    }
}`
	want := `<?php

namespace App;

final class Panel
{
    private string $name = 'x';

    public function all(): array
    {
        return [
            fn () => $this->name,
        ];
    }
}`

	if got := redundantArrowReturnType.fixStable(t, source); got != want {
		t.Fatalf("got\n%s", got)
	}
}

func TestRedundantArrowReturnTypeTakesAClassTypeOffAConstruction(t *testing.T) {
	source := `<?php

namespace App;

final class Money {}

final class Wallet
{
    public function make(): callable
    {
        return fn (): Money => new Money();
    }
}`

	if fixed := redundantArrowReturnType.fixStable(t, source); !strings.Contains(fixed, "return fn () => new Money();") {
		t.Fatalf("got\n%s", fixed)
	}
}

func TestRedundantArrowReturnTypeDoesNotOvershootOntoATypeDoingWork(t *testing.T) {
	source := `<?php

namespace App;

final class Panel
{
    private string $name = 'x';

    public function all(): array
    {
        return [
            fn (): string => $this->name === '' ? 'a' : 'b',
            fn (): ?string => $this->name,
        ];
    }
}`

	if redundantArrowReturnType.rewrote(t, source) {
		t.Fatal("an ambiguous expression and a widening keep their types")
	}
}

func TestRedundantArrowReturnTypeIsIdempotent(t *testing.T) {
	source := `<?php

namespace App;

final class Panel
{
    private int $count = 0;

    public function all(): array
    {
        return [
            fn (): int => $this->count,
            fn (): int => 42,
        ];
    }
}`

	if fixed := redundantArrowReturnType.fixStable(t, source); !strings.Contains(fixed, "fn () => $this->count,") {
		t.Fatalf("got\n%s", fixed)
	}
}
