package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

const documented = `namespace Shop;

public sealed class Basket
{
    /// <summary>The total.</summary>
    /// <param name="rate">The rate.</param>
    /// <see cref="Gone"/>
    public decimal Total(decimal rate) => rate;

    public void Clear()
    {
        // empty the lines before the next order
        Lines = 0;
    }

    public int Lines { get; set; }
}
`

func TestACommentThatDocumentsNothingIsFoundOnItsOwnLine(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Tombstone.cs": "namespace Shop;\n\npublic sealed class Tombstone\n{\n    public void Apply()\n    {\n        // Intentionally empty: the aggregate is removed elsewhere.\n    }\n}\n"})
	found := csharp.In(codebase).WhereComment(func(csharp.Comment) bool { return true })
	if len(found) != 1 || found[0].Line() != 7 || found[0].Scope() != "Line comment" {
		t.Fatalf("the comment is found as %v", found)
	}
}
