package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestABlockingReadOfATaskIsFoundAndAnAwaitIsNotCS holds the rule to the fault it names: an answer taken out
// of work in flight by blocking for it. An await, a constructor and an entry point with no context to
// deadlock against, and a task completed where it stands are how the work is done right and must pass.
func TestABlockingReadOfATaskIsFoundAndAnAwaitIsNotCS(t *testing.T) {
	const feed = `using System.Threading.Tasks;

namespace Shop;

public sealed class Feed
{
    public Task<string> ReadAsync() => Task.FromResult("read");

    public Task SaveAsync() => Task.CompletedTask;
}
`
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"Result on a task": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk(Feed feed)
{
    public string Read() => feed.ReadAsync().Result;
}
`, 1},
		"Wait on a task": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk(Feed feed)
{
    public void Save()
    {
        feed.SaveAsync().Wait();
    }
}
`, 1},
		"GetAwaiter().GetResult() on a task": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk(Feed feed)
{
    public string Read() => feed.ReadAsync().GetAwaiter().GetResult();
}
`, 1},
		"an await": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk(Feed feed)
{
    public async Task<string> ReadAsync() => await feed.ReadAsync();
}
`, 0},
		"a constructor, with no context to deadlock against": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk
{
    private readonly string read;

    public Desk(Feed feed)
    {
        this.read = feed.ReadAsync().Result;
    }
}
`, 0},
		"an entry point": {`using System.Threading.Tasks;

namespace Shop;

public static class Program
{
    public static void Main()
    {
        var feed = new Feed();
        var read = feed.ReadAsync().Result;
    }
}
`, 0},
		"a task completed where it stands": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk
{
    public string Read() => Task.FromResult("read").Result;
}
`, 0},
		"a Result that is not a task's": {`using System.Threading.Tasks;

namespace Shop;

public sealed class Desk
{
    public string Read(Answer answer) => answer.Result;
}

public sealed class Answer
{
    public string Result { get; init; } = "";
}
`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			found := BlockedAwaitDetector{}.Find(csharptest.FromSource(t, map[string]string{"Shop/Desk.cs": test.source, "Shop/Feed.cs": feed}))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
