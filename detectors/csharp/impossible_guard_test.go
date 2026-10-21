package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestAGuardTheTypeRulesOutIsFoundAndARealOneIsNotCS holds the rule to the fault it names: a comparison with
// null on a value the compiler reads as not nullable. A nullable declaration, a value type, and a type the
// scan never saw are all guards that answer a real question and must pass.
func TestAGuardTheTypeRulesOutIsFoundAndARealOneIsNotCS(t *testing.T) {
	const order = `namespace Shop;

public sealed class Order
{
    public string Number { get; init; } = "";
}
`
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a guard the type rules out": {`namespace Shop;

public sealed class Shipping
{
    public string Ship(Order order)
    {
        if (order == null)
        {
            return "none";
        }

        return order.Number;
    }
}
`, 1},
		"the same guard the other way": {`namespace Shop;

public sealed class Shipping
{
    public string Ship(Order order)
    {
        return order != null ? order.Number : "none";
    }
}
`, 1},
		"a nullable declaration": {`namespace Shop;

public sealed class Shipping
{
    public string Ship(Order? order)
    {
        if (order == null)
        {
            return "none";
        }

        return order.Number;
    }
}
`, 0},
		"a value type": {`namespace Shop;

public sealed class Shipping
{
    public string Ship(int number)
    {
        return number == 0 ? "none" : number.ToString();
    }
}
`, 0},
		"a type the scan never saw": {`namespace Shop;

public sealed class Shipping
{
    public string Ship(System.Text.StringBuilder note)
    {
        if (note == null)
        {
            return "none";
        }

        return note.ToString();
    }
}
`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			found := ImpossibleGuardDetector{}.Find(csharptest.FromSource(t, map[string]string{"Shop/Shipping.cs": test.source, "Shop/Order.cs": order}))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
