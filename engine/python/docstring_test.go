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
