package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestACollaboratorNothingReadsIsFoundAndAReadOneIsNotCS holds the rule to the fault it names: a private
// readonly field, or a primary constructor parameter, that nothing in the type reads. A collaborator one
// method reads, a partial type, a type holding a type of its own, and a marked-up field are all how the
// construct earns its place and must pass.
func TestACollaboratorNothingReadsIsFoundAndAReadOneIsNotCS(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a field nothing reads": {`namespace Shop;

public sealed class Desk
{
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public string Post(string to) => to;
}`, 1},
		"a field a method reads": {`namespace Shop;

public sealed class Desk
{
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public void Post(string to) => this.mailer.Send(to);
}`, 0},
		"a primary parameter nothing reads": {`namespace Shop;

public sealed class Desk(Mailer mailer)
{
    public string Post(string to) => to;
}`, 1},
		"a primary parameter a method reads": {`namespace Shop;

public sealed class Desk(Mailer mailer)
{
    public void Post(string to) => mailer.Send(to);
}`, 0},
		"a partial type": {`namespace Shop;

public sealed partial class Desk
{
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public string Post(string to) => to;
}`, 0},
		"a type holding a type of its own": {`namespace Shop;

public sealed class Desk
{
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public string Post(string to) => to;

    private sealed class Slip
    {
    }
}`, 0},
		"a marked-up field": {`namespace Shop;

public sealed class Desk
{
    [System.Obsolete]
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public string Post(string to) => to;
}`, 0},
		"a field read by name only": {`namespace Shop;

public sealed class Desk
{
    private readonly Mailer mailer;

    public Desk(Mailer mailer)
    {
        this.mailer = mailer;
    }

    public string Post(string to) => nameof(this.mailer) + to;
}`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			sources := map[string]string{
				"Shop/Desk.cs":   test.source,
				"Shop/Mailer.cs": "namespace Shop;\n\npublic sealed class Mailer\n{\n    public void Send(string to)\n    {\n    }\n}\n",
			}
			found := UnreadCollaboratorDetector{}.Find(csharptest.FromSource(t, sources))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
