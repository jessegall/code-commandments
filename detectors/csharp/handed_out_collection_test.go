package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestAHandedOutCollectionIsFoundAndAGuardedOneIsNotCS holds the rule to the fault it names: a property or
// method whose whole body returns the type's own List or Dictionary. A read-only declaration, a copy, and a
// collection the type was handed rather than built are how a type gives its collection away safely.
func TestAHandedOutCollectionIsFoundAndAGuardedOneIsNotCS(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a list handed out by a property": {`namespace Shop;

public sealed class Desk
{
    private readonly List<string> notes = new();

    public List<string> Notes => this.notes;
}
`, 1},
		"a dictionary handed out by a method": {`namespace Shop;

public sealed class Desk
{
    private readonly Dictionary<string, int> counts = new();

    public Dictionary<string, int> Counts()
    {
        return this.counts;
    }
}
`, 1},
		"a read-only declaration": {`namespace Shop;

public sealed class Desk
{
    private readonly List<string> notes = new();

    public IReadOnlyList<string> Notes => this.notes;
}
`, 0},
		"a copy handed out": {`namespace Shop;

public sealed class Desk
{
    private readonly List<string> notes = new();

    public List<string> Notes => this.notes.ToList();
}
`, 0},
		"a count, not the collection": {`namespace Shop;

public sealed class Desk
{
    private readonly List<string> notes = new();

    public int Waiting => this.notes.Count;
}
`, 0},
		"a chain that builds it": {`namespace Shop;

public sealed class VariableMap
{
    private readonly Dictionary<string, object> data = new();

    public VariableMap Set(string key, object value)
    {
        this.data[key] = value;

        return this;
    }

    public VariableMap SetAll(IReadOnlyDictionary<string, object> values)
    {
        foreach (var held in values)
        {
            this.data[held.Key] = held.Value;
        }

        return this;
    }

    public Dictionary<string, object> Build() => this.data;
}
`, 0},
		"a collection it was handed": {`namespace Shop;

public sealed class Desk(List<string> notes)
{
    private readonly List<string> held = notes;

    public List<string> Notes => this.held;
}
`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			found := HandedOutCollectionDetector{}.Find(csharptest.FromSource(t, map[string]string{"Shop/Desk.cs": test.source}))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
