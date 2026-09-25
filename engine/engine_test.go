package engine_test

import (
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

const slack = "/fixtures/backend/app/Notifications/SlackNotifier.php"

// sample is the codebase the PHP sample stream describes, its sources read from tests/Fixtures.
func sample(t *testing.T) *engine.Codebase {
	t.Helper()
	file, err := os.Open("../contract/samples/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer file.Close()
	stream, err := contract.ReadAll(file)
	if err != nil {
		t.Fatal(err)
	}

	return engine.New(func(path string) ([]byte, error) {
		return os.ReadFile("../tests/Fixtures/" + strings.TrimPrefix(path, "/fixtures/"))
	}, stream)
}

// Facades is a toy package node, standing in for the decorators the language layers declare.
type Facades struct {
	engine.Match
}

func (Facades) Decorate(m engine.Match) Facades {
	return Facades{m}
}

func (f Facades) IsFunctionCall() bool {
	return f.Kind() == "Expr_FuncCall"
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

func TestAsAsksInADecoratorsTerms(t *testing.T) {
	count := sample(t).
		WhereCall().
		Where(engine.As(Facades.IsFunctionCall)).
		Count()

	if count != 4 {
		t.Fatalf("got %d function calls, want 4", count)
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
	if scope := call.EnclosingType().Scope(); scope != "(file)" {
		t.Fatalf("a type at file level scopes to (file), got %s", scope)
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
	stream, err := os.ReadFile("../contract/samples/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	codebase, err := engine.FromString(string(stream), map[string]string{slack: "<?php"})
	if err != nil {
		t.Fatal(err)
	}

	for _, file := range codebase.Files() {
		source, err := file.Source()
		if (file.Path == slack) != (err == nil) || (err == nil && string(source) != "<?php") {
			t.Fatalf("%s: got %q, %v", file.Path, source, err)
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
		if earlier.Node().Field == node.Node().Field {
			continue
		}
		compared++
		if !earlier.SameSyntax(node) {
			t.Errorf("%s in %s and in %s are not the same syntax", key, earlier.Node().Field, node.Node().Field)
		}
	}
	if compared == 0 {
		t.Fatal("the sample writes no name twice in different slots")
	}
}
