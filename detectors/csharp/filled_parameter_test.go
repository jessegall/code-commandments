package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestAFilledParameterIsFoundAndACommandIsNotCS holds the rule to the fault it names: a method returning
// nothing that writes a property of what it was handed, whose caller reads that property back off. A command
// the caller never looks at again, a method that returns its answer, and an `out` parameter, which says in the
// signature that the value comes back, are how the work is done right and must pass.
func TestAFilledParameterIsFoundAndACommandIsNotCS(t *testing.T) {
	const slip = `namespace Shop;

public sealed class Slip
{
    public int Net { get; set; }

    public bool Sealed { get; set; }
}
`
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"filled, then read back": {`namespace Shop;

public sealed class Pricer
{
    public int Price(IReadOnlyList<int> lines)
    {
        var slip = new Slip();
        this.AddLines(slip, lines);

        return slip.Net;
    }

    private void AddLines(Slip slip, IReadOnlyList<int> lines)
    {
        slip.Net = lines.Sum();
    }
}
`, 1},
		"a command nobody reads back": {`namespace Shop;

public sealed class Pricer
{
    public void Close(Slip slip)
    {
        this.Seal(slip);
    }

    private void Seal(Slip slip)
    {
        slip.Sealed = true;
    }
}
`, 0},
		"the answer returned": {`namespace Shop;

public sealed class Pricer
{
    public int Price(IReadOnlyList<int> lines)
    {
        var slip = new Slip();
        slip.Net = this.NetOf(lines);

        return slip.Net;
    }

    private int NetOf(IReadOnlyList<int> lines) => lines.Sum();
}
`, 0},
		"an out parameter says so": {`namespace Shop;

public sealed class Pricer
{
    public int Price(IReadOnlyList<int> lines)
    {
        this.AddLines(out var slip, lines);

        return slip.Net;
    }

    private void AddLines(out Slip slip, IReadOnlyList<int> lines)
    {
        slip = new Slip();
        slip.Net = lines.Sum();
    }
}
`, 0},
		"filled with no caller at all": {`namespace Shop;

public sealed class Pricer
{
    private void AddLines(Slip slip, IReadOnlyList<int> lines)
    {
        slip.Net = lines.Sum();
    }
}
`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			sources := map[string]string{"Shop/Pricer.cs": test.source, "Shop/Slip.cs": slip}
			found := FilledParameterDetector{}.Find(csharptest.FromSource(t, sources))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
