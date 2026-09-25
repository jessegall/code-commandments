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

func TestADocCommentReadsAsItsTags(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Basket.cs": documented})
	comments := csharp.In(codebase).Comments()
	doc := comments[0]
	tags := doc.Tags()
	if len(tags) != 3 || tags[0].Name != "summary" || tags[1].Name != "param" {
		t.Fatalf("the doc comment reads as %v", tags)
	}
	if doc.Paragraphs() != 1 {
		t.Errorf("the summary is %d paragraphs", doc.Paragraphs())
	}
	if documented := doc.Documented(); !documented.Is("MethodDeclaration") || doc.Line() != 8 {
		t.Errorf("the doc comment documents %s on line %d", documented.Kind(), doc.Line())
	}
	if refs := doc.Refs; len(refs) != 1 || !csharp.IsDangling(refs[0]) {
		t.Errorf("the reference to Gone is not dangling: %+v", refs)
	}
}

func TestTheCommentsAboveAStatementSayItsWords(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Basket.cs": documented})
	statement := csharptest.First(t, codebase, "Shop/Basket.cs", "ExpressionStatement")
	words := statement.CommentWords()
	if len(words) == 0 || len(statement.CodeWords()) == 0 {
		t.Errorf("the comment says %v and the statement %v", words, statement.CodeWords())
	}
}
