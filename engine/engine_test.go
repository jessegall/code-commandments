package engine_test

import (
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

var slack = shop.Root + "/app/Notifications/SlackNotifier.php"

// sampled are the ten shop files the engine's own tests ask about.
var sampled = []string{"Billing/Checkout.php", "Fulfillment/LabelPrintDefaults.php", "Fulfillment/LegChain.php",
	"Http/Pages/Hydration/OptCoords.php", "Notifications/SlackNotifier.php", "Orders/BasketTotaller.php",
	"Orders/DiscountTier.php", "Reporting/AccessAuditor.php", "Reporting/GradeCalculator.php", "Shipping/CarrierPicker.php"}

func sample(t *testing.T) *engine.Codebase {
	t.Helper()
	paths := make([]string, len(sampled))
	for at, each := range sampled {
		paths[at] = "app/" + each
	}

	return shop.Files(t, paths...)
}

func TestWhereKeepsWhatEveryCheckAnswersYesTo(t *testing.T) {
	locations := sample(t).
		WhereCall().
		Where(engine.Match.IsWithinLoop).
		Locations()

	if !slices.Equal(locations, []string{slack + ":27"}) {
		t.Fatalf("got %v", locations)
	}
}

func TestRejectDropsWhatTheCheckAnswersYesTo(t *testing.T) {
	count := sample(t).
		WhereCall().
		Reject(engine.Match.IsWithinLoop).
		Count()

	if count != 6 {
		t.Fatalf("got %d calls outside a loop, want 6", count)
	}
}

func TestFirstIsNoNodeWhenNothingPasses(t *testing.T) {
	first := sample(t).
		WhereNew().
		Where(engine.Match.IsWithinLoop).
		First()

	if first.Exists() || first.Parent().Exists() || first.Location() != ":0" {
		t.Fatalf("got %v", first.Location())
	}
}

func TestAMatchKnowsItsScope(t *testing.T) {
	call := sample(t).
		WhereCall().
		Where(engine.Match.IsWithinLoop).
		First()

	if scope := call.Scope(); scope != `Shop\Notifications\SlackNotifier::ping` {
		t.Fatalf("got %s", scope)
	}
	if scope := call.EnclosingType().Scope(); scope != `Shop\Notifications\SlackNotifier` {
		t.Fatalf("a type names itself, as PHP's enclosingClass does, got %s", scope)
	}
	if scope := call.EnclosingFunction().Scope(); scope != `Shop\Notifications\SlackNotifier::ping` {
		t.Fatalf("a method names itself, as PHP's enclosingFunctionName does, got %s", scope)
	}
}

func TestAMatchSpansItsSource(t *testing.T) {
	construction := sample(t).WhereNew().First()

	span, err := construction.Span()
	if err != nil {
		t.Fatal(err)
	}
	if !strings.HasPrefix(span.Text(), "new ") || span.Line() != construction.Line() {
		t.Fatalf("got %q on line %d", span.Text(), span.Line())
	}
	whole, err := construction.EnclosingType().Span()
	if err != nil {
		t.Fatal(err)
	}
	if !whole.Contains(span) || span.Contains(whole) || whole.Contains(whole) {
		t.Fatal("a class strictly contains its construction, and nothing contains itself")
	}
}

func TestADeclarationKnowsItsDocComment(t *testing.T) {
	documented := sample(t).
		WhereTypeDeclaration().
		Where(engine.Match.IsDocumented).
		Count()
	all := sample(t).WhereTypeDeclaration().Count()

	if documented == 0 || documented > all {
		t.Fatalf("got %d documented of %d", documented, all)
	}
}

func TestOfKeepsOneLanguage(t *testing.T) {
	codebase := sample(t)

	if len(codebase.Of(contract.Python).Files()) != 0 || len(codebase.Of(contract.PHP).Files()) != len(codebase.Files()) {
		t.Fatal("the PHP sample is all PHP and no Python")
	}
}

func TestFromStringReadsSourcesByPath(t *testing.T) {
	stream, err := os.ReadFile("../contract/samples/testdata/expected/elif/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	given := strings.Repeat("\n", 100_000)
	const elif = "/expected/elif/php.php"

	codebase, err := engine.FromString(string(stream), map[string]string{elif: given})
	if err != nil {
		t.Fatal(err)
	}

	for _, file := range codebase.Files() {
		source, err := file.Source()
		if (file.Path == elif) != (err == nil) || (err == nil && string(source) != given) {
			t.Fatalf("%s: got %d bytes, %v", file.Path, len(source), err)
		}
	}
}

func TestTwoNodesWrittenAlikeAreTheSameSyntaxWhereverTheySit(t *testing.T) {
	seen := map[string]engine.Match{}
	compared := 0
	for _, node := range sample(t).Where(func(m engine.Match) bool { return len(m.Children()) == 0 && m.Name() != "" }).Get() {
		key := node.Kind() + " " + node.Name()
		earlier, ok := seen[key]
		if !ok {
			seen[key] = node
			continue
		}
		if earlier.Node().InRole() == node.Node().InRole() {
			continue
		}
		compared++
		if !earlier.SameSyntax(node) {
			t.Errorf("%s in %s and in %s are not the same syntax", key, earlier.Node().InRole(), node.Node().InRole())
		}
	}
	if compared == 0 {
		t.Fatal("the sample writes no name twice in different slots")
	}
}
