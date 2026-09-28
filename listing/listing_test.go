package listing

import (
	"math/rand"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
)

// hydration is tests/Fixtures/backend/app/Http/Pages/Hydration as macOS lists it (`ls -f`), the order the
// PHP tool met it in when the parity goldens were recorded.
var hydration = []string{
	"RoundtripHeader.php", "OptCoords.php", "UnwrapPaymentIntent.php", "MoveRequest.php",
	"CollTypedRighteous.php", "RemapContract.php", "NestCollection.php", "EnumSlotRighteous.php",
	"PlacementFactory.php", "WireNode.php", "WirePanel.php", "WireComputedRighteous.php",
}

// app is tests/Fixtures/backend/app as macOS lists it: folders, whose names hash the same way.
var app = []string{
	"Customers", "Ui", "Reporting", "Payments", "Wire", "Sockets", "Auth", "Casts", "Dispatch", "Contracts",
	"Providers", "Catalog", "Enums", "Labels", "Fulfillment", "Legacy", "Repositories", "Mcp", "Models",
}

func TestNamesAreOrderedAsMacOSListsThemWhateverOrderTheyArrive(t *testing.T) {
	for _, want := range [][]string{hydration, app} {
		shuffled := slices.Clone(want)
		rand.New(rand.NewSource(19)).Shuffle(len(shuffled), func(i, j int) {
			shuffled[i], shuffled[j] = shuffled[j], shuffled[i]
		})

		for _, arrived := range [][]string{want, reversedOf(want), shuffled, sortedByName(want)} {
			if got := Ordered(arrived); !slices.Equal(got, want) {
				t.Errorf("Ordered(%q)\n = %q\nwant %q", arrived, got, want)
			}
		}
	}
}

func TestAFolderIsListedInTheSameOrderWhateverOrderItsEntriesWereMade(t *testing.T) {
	for _, made := range [][]string{hydration, reversedOf(hydration)} {
		dir := t.TempDir()

		for _, name := range made {
			if err := os.WriteFile(filepath.Join(dir, name), nil, 0o644); err != nil {
				t.Fatal(err)
			}
		}

		if got := Of(dir); !slices.Equal(got, hydration) {
			t.Errorf("Of(folder made as %q)\n = %q\nwant %q", made, got, hydration)
		}
	}
}

func TestANameHashesAsItsCaseFoldedSelf(t *testing.T) {
	upper := make([]string, len(hydration))

	for i, name := range hydration {
		upper[i] = strings.ToUpper(name)
	}

	if got := Ordered(reversedOf(upper)); !slices.Equal(got, upper) {
		t.Errorf("Ordered(upper case) = %q\nwant %q", got, upper)
	}
}

func TestAFolderThatIsNotThereListsNothing(t *testing.T) {
	if got := Of(filepath.Join(t.TempDir(), "missing")); len(got) != 0 {
		t.Errorf("Of(missing) = %q", got)
	}
}

func reversedOf(names []string) []string {
	reversed := slices.Clone(names)
	slices.Reverse(reversed)

	return reversed
}

func sortedByName(names []string) []string {
	sorted := slices.Clone(names)
	slices.Sort(sorted)

	return sorted
}
