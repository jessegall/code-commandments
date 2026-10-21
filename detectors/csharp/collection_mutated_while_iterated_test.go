package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// wrapped is the source as one class in a file the C# bridge reads.
func wrapped(body string) string {
	return `using System.Collections.Generic;
using System.Linq;

namespace Shop;

public sealed class Desk
{
    private readonly List<string> notes = new();

` + body + `
}
`
}

// TestAChangeToTheWalkedCollectionIsFoundAndACopyIsNotCS holds the rule to the fault it names: a foreach that
// adds to or removes from the collection it is walking reads an enumerator that has moved and throws. Changing
// a different collection, walking a copy, and a change that leaves the loop at once are how the work is done
// right and must pass.
func TestAChangeToTheWalkedCollectionIsFoundAndACopyIsNotCS(t *testing.T) {
	for name, test := range map[string]struct {
		body string
		want int
	}{
		"removing from the list it walks": {`    public void Tidy(string dropped)
    {
        foreach (var note in this.notes)
        {
            if (note == dropped)
            {
                this.notes.Remove(note);
            }
        }
    }`, 1},
		"adding to the list it walks": {`    public void Grow()
    {
        foreach (var note in this.notes)
        {
            this.notes.Add(note + "!");
        }
    }`, 1},
		"clearing a local it walks": {`    public void Clear(List<string> lines)
    {
        foreach (var line in lines)
        {
            lines.Clear();
        }
    }`, 1},
		"changing a mapping it walks by key": {`    public void Sweep(Dictionary<string, int> counts)
    {
        foreach (var code in counts.Keys)
        {
            counts.Remove(code);
        }
    }`, 1},
		"changing it from an inner loop": {`    public void Drop(List<string> keys)
    {
        foreach (var note in this.notes)
        {
            foreach (var key in keys)
            {
                this.notes.Remove(key);
            }
        }
    }`, 1},
		"changing a different collection": {`    public void Copy(List<string> into)
    {
        foreach (var note in this.notes)
        {
            into.Add(note);
        }
    }`, 0},
		"walking a copy": {`    public void Tidy(string dropped)
    {
        foreach (var note in this.notes.ToList())
        {
            if (note == dropped)
            {
                this.notes.Remove(note);
            }
        }
    }`, 0},
		"removing then breaking at once": {`    public void Take(string wanted)
    {
        foreach (var note in this.notes)
        {
            if (note == wanted)
            {
                this.notes.Remove(note);
                break;
            }
        }
    }`, 0},
		"removing then returning at once": {`    public bool Take(string wanted)
    {
        foreach (var note in this.notes)
        {
            if (note == wanted)
            {
                this.notes.Remove(note);
                return true;
            }
        }

        return false;
    }`, 0},
		"removing then breaking the inner": {`    public void Drop(List<string> keys)
    {
        foreach (var note in this.notes)
        {
            foreach (var key in keys)
            {
                this.notes.Remove(key);
                break;
            }
        }
    }`, 1},
		"pruning a concurrent dictionary": {`    public void Prune(System.Collections.Concurrent.ConcurrentDictionary<string, int> processed)
    {
        foreach (var held in processed)
        {
            if (held.Value == 0)
            {
                processed.TryRemove(held.Key, out _);
            }
        }
    }`, 0},
		"changing it after the loop": {`    public void Tidy()
    {
        foreach (var note in this.notes)
        {
        }

        this.notes.Clear();
    }`, 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := csharptest.FromSource(t, map[string]string{"Shop/Desk.cs": wrapped(test.body)})
			found := CollectionMutatedWhileIteratedDetector{}.Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
