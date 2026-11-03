package rule

import (
	"compress/gzip"
	"io"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

// A generated vocabulary is only worth having while it is true: a bridge that starts emitting a kind, a field, an
// operator, a modifier or a flag the committed file does not list makes every surface read from it — the schema's
// autocomplete, the suggester's "did you mean" — a lie that reads like coverage. So the suite fails until the
// census is run again, which is the same shape the contract's own samples are held to.

// TestEveryRecordedStreamIsInTheVocabulary holds every committed vocabulary to the streams it was read from: what
// a stream carries, the vocabulary lists.
func TestEveryRecordedStreamIsInTheVocabulary(t *testing.T) {
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	for _, source := range VocabularySources {
		stream := recorded(t, source)
		for _, file := range stream.Files {
			vocabulary, held := committed[file.Language]
			if !held {
				t.Errorf("%s carries %s, which no vocabulary covers: run the census", source, file.Language)

				continue
			}

			for _, node := range file.Nodes() {
				holds(t, vocabulary, node, source)
			}
		}
	}
}

// holds reports every fact the node carries that its construct's row does not list.
func holds(t *testing.T, vocabulary Vocabulary, node *contract.Node, source string) {
	t.Helper()

	construct, listed := vocabulary.Construct(node.Kind)
	if !listed {
		t.Errorf("%s carries the kind %q, which the %s vocabulary does not list: run the census", source, node.Kind, vocabulary.Language)

		return
	}

	for name, carried := range map[string][]string{"role": {node.Role}, "is": node.Is, "modifier": node.Modifiers, "flag": node.Flags} {
		listed := map[string][]string{"role": construct.Roles, "is": construct.Is, "modifier": construct.Modifiers, "flag": construct.Flags}[name]
		for _, value := range carried {
			if !slices.Contains(listed, value) {
				t.Errorf("%s writes %q with the %s %q, which the vocabulary does not list: run the census", source, node.Kind, name, value)
			}
		}
	}

	if node.Operator != "" && !slices.Contains(construct.Operators, node.Operator) {
		t.Errorf("%s writes %q with the operator %q, which the vocabulary does not list: run the census", source, node.Kind, node.Operator)
	}

	for _, child := range node.Children {
		if child.Field != "" && !slices.Contains(construct.Fields, child.Field) {
			t.Errorf("%s writes %q with a child in the slot %q, which the vocabulary does not list: run the census", source, node.Kind, child.Field)
		}
	}
}

// TestAVocabularySaysWhereItsRowsCameFrom holds the two things that make a generated file readable: which streams
// it was read from, and that every row says who vouches for it.
func TestAVocabularySaysWhereItsRowsCameFrom(t *testing.T) {
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	if len(committed) == 0 {
		t.Fatal("no vocabulary is committed, and the census reads five languages")
	}

	for language, vocabulary := range committed {
		if len(vocabulary.Sources) == 0 || len(vocabulary.Bridges) == 0 || len(vocabulary.Constructs) == 0 {
			t.Errorf("%s names %d sources, %d bridges and %d constructs", language, len(vocabulary.Sources), len(vocabulary.Bridges), len(vocabulary.Constructs))
		}

		for _, construct := range vocabulary.Constructs {
			if len(construct.Vouched) == 0 || len(construct.Roles) == 0 {
				t.Errorf("%s %q is vouched by %v in the roles %v", language, construct.Kind, construct.Vouched, construct.Roles)
			}

			if slices.Contains(construct.Vouched, "observed") && construct.Example == "" {
				t.Errorf("%s %q is observed and says nowhere it was seen", language, construct.Kind)
			}
		}
	}
}

// recorded is the stream the source holds, read through the contract's own reader.
func recorded(t *testing.T, source string) *contract.Stream {
	t.Helper()

	handle, err := os.Open(filepath.Join("..", source))
	if err != nil {
		t.Fatalf("%s: %v", source, err)
	}
	defer handle.Close()

	var reader io.Reader = handle
	if strings.HasSuffix(source, ".gz") {
		unpacked, err := gzip.NewReader(handle)
		if err != nil {
			t.Fatalf("%s: %v", source, err)
		}
		defer unpacked.Close()
		reader = unpacked
	}

	stream, err := contract.ReadAll(reader)
	if err != nil {
		t.Fatalf("%s breaks the contract: %v", source, err)
	}

	return stream
}

// A kind is the language's own word and a rule may write it as it is, but `Expr_StaticCall` is nobody's idea of a
// readable rule. The spelling beside it is derived mechanically, so a bridge that adds a kind gets one without
// anybody deciding, and the one thing that could make such a table lie — two kinds spelling the same — is handled
// mechanically too: both lose their spelling and are named as collided.

// TestEverySpellingNamesOneKind holds the table unambiguous and readable back: no two kinds share a spelling, each
// spelling answers its own kind, and a kind that collided says so instead of carrying a word that means two
// things.
func TestEverySpellingNamesOneKind(t *testing.T) {
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	for language, vocabulary := range committed {
		unambiguous(t, language, vocabulary)

		for spelling, kinds := range vocabulary.Collided {
			if len(kinds) < 2 {
				t.Errorf("%s says %q collided between %v", language, spelling, kinds)
			}
		}
	}
}

// unambiguous reports every spelling of the language that names more than one kind, that names a kind other than
// its own, or that is missing from a kind nothing collided with.
func unambiguous(t *testing.T, language contract.Language, vocabulary Vocabulary) {
	t.Helper()

	spelled := map[string]string{}
	for _, construct := range vocabulary.Constructs {
		_, collided := vocabulary.Collided[Spelling(construct.Kind)]
		if construct.Spells == "" && !collided {
			t.Errorf("%s %q has no spelling and collided with nothing", language, construct.Kind)

			continue
		}

		if construct.Spells == "" {
			continue
		}

		if held, taken := spelled[construct.Spells]; taken {
			t.Errorf("%s spells %q and %q both %q", language, held, construct.Kind, construct.Spells)
		}
		spelled[construct.Spells] = construct.Kind

		if construct.Spells != Spelling(construct.Kind) {
			t.Errorf("%s spells %q as %q, and the spelling of that kind is %q", language, construct.Kind, construct.Spells, Spelling(construct.Kind))
		}

		if read, found := vocabulary.Spelled(construct.Spells); !found || read.Kind != construct.Kind {
			t.Errorf("%s reads %q back as %q", language, construct.Spells, read.Kind)
		}
	}
}

// TestASpellingDropsWhatTheRoleAlreadySays holds the derivation itself, with the shapes each language pads a kind
// with, and holds it to leaving a kind that says nothing twice alone.
func TestASpellingDropsWhatTheRoleAlreadySays(t *testing.T) {
	for kind, spelling := range map[string]string{
		"Expr_StaticCall":          "static-call",
		"Stmt_ClassMethod":         "class-method",
		"Scalar_String":            "string",
		"InvocationExpression":     "invocation",
		"IfStatement":              "if",
		"PropertyDeclaration":      "property",
		"ListComp":                 "list-comp",
		"Name_FullyQualified":      "name-fully-qualified",
		"AnnAssign":                "ann-assign",
		"ForEachVariableStatement": "for-each-variable",
		// A kind that is only padding keeps its word rather than becoming nothing.
		"Expression": "expression",
		"Statement":  "statement",
	} {
		if got := Spelling(kind); got != spelling {
			t.Errorf("%s spells %q, want %q", kind, got, spelling)
		}
	}
}

// TestTwoKindsSpellingTheSameLoseTheirSpelling holds the collision reading, which is what the generator clears a
// spelling by, on a pair written here on purpose.
func TestTwoKindsSpellingTheSameLoseTheirSpelling(t *testing.T) {
	colliding := Vocabulary{Constructs: []Construct{
		{Kind: "VariableDeclaration", Spells: "variable"},
		{Kind: "VariableStatement", Spells: "variable"},
		{Kind: "IfStatement", Spells: "if"},
	}}

	collisions := colliding.Collisions()
	if len(collisions) != 1 || len(collisions["variable"]) != 2 {
		t.Errorf("the collisions are %v, and the two variable kinds are the only one", collisions)
	}

	// Every language the census reads really has such a pair or really has none, which the committed files say;
	// what matters is that a collision is never written as a spelling.
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	for language, vocabulary := range committed {
		written(t, language, vocabulary)
	}
}

// written reports a spelling the vocabulary wrote although that spelling collided.
func written(t *testing.T, language contract.Language, vocabulary Vocabulary) {
	t.Helper()

	for _, construct := range vocabulary.Constructs {
		if _, collided := vocabulary.Collided[construct.Spells]; collided && construct.Spells != "" {
			t.Errorf("%s writes %q as the spelling of %q, and it collided", language, construct.Spells, construct.Kind)
		}
	}
}
