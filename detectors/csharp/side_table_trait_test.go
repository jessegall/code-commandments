package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

const registry = `using System.Collections.Generic;
using System.Linq;

namespace Shop;

public sealed record Tag(string Name, string Runs);
`

// TestASideTableOfRegisteredNamesIsFoundCS holds the three things that together tell a side table from an
// ordinary collection of words: the names are the file's own registry's, the file reaches for the instances
// through them, and a collection derived from the registry or naming none of them is left alone.
func TestASideTableOfRegisteredNamesIsFoundCS(t *testing.T) {
	for name, source := range map[string]string{
		"a set asked whether it holds one": registry + `
public static class Tags
{
    public static readonly Tag[] All = { new Tag("reply", "a"), new Tag("log", "b"), new Tag("todo", "c") };

    private static readonly HashSet<string> Quiet = new() { "reply", "log" };

    public static bool Said(string name) => !Quiet.Contains(name);
}
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(csharptest.FromSource(t, map[string]string{"Shop/Tags.cs": source}))

			if len(found) != 1 {
				t.Fatalf("found %d side tables, want 1: %v", len(found), locations(found))
			}
		})
	}
}

// TestACollectionThatIsNotASideTableIsLeftAloneCS holds the negatives, each wearing the same surface shape.
func TestACollectionThatIsNotASideTableIsLeftAloneCS(t *testing.T) {
	for name, source := range map[string]string{
		"ordinary vocabulary naming no instance": registry + `
public static class Tags
{
    public static readonly Tag[] All = { new Tag("reply", "a"), new Tag("log", "b") };

    private static readonly HashSet<string> ShellTools = new() { "exec", "shell" };

    public static bool Shelled(string name) => ShellTools.Contains(name);
}
`,
		"walked to act on each, which a caller choosing what to act on also does": registry + `
public static class Tags
{
    public static readonly Tag[] All = { new Tag("reply", "a"), new Tag("log", "b") };

    private static readonly List<string> Held = new() { "reply" };

    public static void Shown(IBoard board)
    {
        foreach (var name in Held) board.Rows(name);
    }
}

public interface IBoard { void Rows(string name); }
`,
		"held but never used as a handle": registry + `
public static class Tags
{
    public static readonly Tag[] All = { new Tag("reply", "a"), new Tag("log", "b") };

    private static readonly HashSet<string> Order = new() { "reply", "log" };
}
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(csharptest.FromSource(t, map[string]string{"Shop/Tags.cs": source}))

			if len(found) != 0 {
				t.Errorf("flagged %d, want none: %v", len(found), locations(found))
			}
		})
	}
}

func locations(found []engine.Match) []string {
	where := make([]string, 0, len(found))
	for _, match := range found {
		where = append(where, match.Location())
	}

	return where
}
