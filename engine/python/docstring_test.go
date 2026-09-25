package python_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

const pricing = `# the shop's pricing
RATE = 3  # a trailing note


# formerly lived in checkout
# moved here in the refactor
def price(order):
    """Price an order."""
    # add the rate
    return order.total + RATE


class Cart:
    '''A basket of lines.

    Held per session. See :class:` + "`shop.cart.Cart`" + ` and :func:` + "`~shop.pricing.price`" + `.
    '''

    def empty(self):
        pass
`

func texts(comments []contract.Comment) string {
	var words []string
	for _, comment := range comments {
		words = append(words, comment.Text)
	}

	return strings.Join(words, "|")
}

func TestTheRunOfOwnLineCommentsDirectlyAboveAStatementIsItsOwn(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/pricing.py": pricing})
	nodes := map[string]python.Node{}
	for _, match := range pythontest.File(t, codebase, "shop/pricing.py").Match(0).Descendants() {
		node := python.Node{Match: match}
		if _, seen := nodes[node.Kind()]; !seen {
			nodes[node.Kind()] = node
		}
	}
	above := map[string]string{
		"FunctionDef": "# formerly lived in checkout|# moved here in the refactor",
		"Return":      "# add the rate",
		"ClassDef":    "",
		"Assign":      "# the shop's pricing",
	}
	for kind, want := range above {
		if got := texts(nodes[kind].CommentsAbove()); got != want {
			t.Errorf("above the %s: %q, not %q", kind, got, want)
		}
	}
	if docstring, _ := nodes["FunctionDef"].Docstring(); docstring != "Price an order." {
		t.Errorf("the def's docstring is %q", docstring)
	}
	if docstring, _ := nodes["ClassDef"].Docstring(); !strings.HasPrefix(docstring, "A basket of lines.\n") {
		t.Errorf("the class's docstring is %q", docstring)
	}
	if _, ok := nodes["Return"].Docstring(); ok {
		t.Error("a return has a docstring")
	}
}

func TestADocstringsCrossReferencesAreResolvedWhereTheCodebaseOwnsThem(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/pricing.py": pricing})
	refs := map[string]contract.Ref{}
	for _, comment := range pythontest.File(t, codebase, "shop/pricing.py").File.Comments {
		for _, ref := range comment.Refs {
			refs[ref.Text] = ref
		}
	}
	if ref := refs["shop.pricing.price"]; ref.Symbol != "shop.pricing.price" || !*ref.OwnedHere {
		t.Errorf("the reference to price is %+v", ref)
	}
	if ref := refs["shop.cart.Cart"]; ref.Symbol != "" || !*ref.OwnedHere {
		t.Errorf("the dangling reference to shop.cart.Cart is %+v", ref)
	}
}

func TestADocstringIsReadAsProseOrAsARestatement(t *testing.T) {
	text := "Price an order.\n\n    Held per session.\n\n    .. versionadded:: 2.0\n        The rate.\n\n    Args:\n        order: the order.\n    "
	if paragraphs := python.ProseParagraphs(text); paragraphs != 2 {
		t.Errorf("%d paragraphs of prose", paragraphs)
	}
	if !python.OnlyRestates("Args:\n    order:\nReturns:\n    int", []string{"order"}, true) {
		t.Error("a bare Args and Returns restate the signature")
	}
	if python.OnlyRestates("Price it.\nArgs:\n    order:", []string{"order"}, true) {
		t.Error("a summary is no restatement")
	}
	if refs := python.References(":class:`~shop.Cart` and :func:`total <shop.pricing.total>` and :func:`bare`"); strings.Join(refs, ",") != "shop.Cart,shop.pricing.total" {
		t.Errorf("the references are %v", refs)
	}
}

func TestAStatementSpellsItsConstructAndItsOwnHead(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"loop.py": `
def settle(orders):
    for order in orders.pending:
        order.save(force=True)
`})
	for _, match := range pythontest.File(t, codebase, "loop.py").Match(0).Descendants() {
		node := python.Node{Match: match}
		if node.Kind() != "For" {
			continue
		}
		if words := strings.Join(node.CodeWords(), " "); words != "loop iterat every order pend" {
			t.Errorf("the loop spells %q", words)
		}
	}
}
