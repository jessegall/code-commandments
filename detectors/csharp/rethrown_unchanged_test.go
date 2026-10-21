package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// held is the method body as one class in a file the C# bridge reads.
func held(body string) string {
	return `namespace Shop;

public sealed class Desk
{
` + body + `

    private void Work()
    {
    }
}
`
}

// TestACatchThatOnlyThrowsAgainIsFoundAndOneThatWorksIsNotCS holds the rule to the fault it names: a clause
// that catches a failure, adds nothing and throws it again is a clause nobody needed. A clause that does real
// work first, one that throws a failure of its own, and a narrow clause shielding a broader one below it are
// how the construct earns its place and must pass.
func TestACatchThatOnlyThrowsAgainIsFoundAndOneThatWorksIsNotCS(t *testing.T) {
	for name, test := range map[string]struct {
		body string
		want int
	}{
		"a bare throw": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException)
        {
            throw;
        }
    }`, 1},
		"throwing the caught variable": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException error)
        {
            throw error;
        }
    }`, 1},
		"a bare throw beside a finally": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException)
        {
            throw;
        }
        finally
        {
            this.Work();
        }
    }`, 1},
		"work before the throw": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException)
        {
            this.Work();
            throw;
        }
    }`, 0},
		"throwing a failure of its own": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException error)
        {
            throw new System.ApplicationException("no", error);
        }
    }`, 0},
		"shielding a broader clause": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.OperationCanceledException)
        {
            throw;
        }
        catch (System.Exception)
        {
            this.Work();
        }
    }`, 0},
		"the last of several clauses": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.OperationCanceledException)
        {
            this.Work();
        }
        catch (System.Exception)
        {
            throw;
        }
    }`, 1},
		"handling without throwing": {`    public void Run()
    {
        try
        {
            this.Work();
        }
        catch (System.InvalidOperationException)
        {
            this.Work();
        }
    }`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := csharptest.FromSource(t, map[string]string{"Shop/Desk.cs": held(test.body)})
			found := RethrownUnchangedDetector{}.Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
