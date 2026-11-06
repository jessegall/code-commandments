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

func TestAVocabularySaysWhereItsRowsCameFrom(t *testing.T) {
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	if len(committed) == 0 {
		t.Fatal("no vocabulary is committed, and the census reads six languages")
	}

	for language, vocabulary := range committed {
		if len(vocabulary.Constructs) == 0 {
			t.Errorf("%s names no constructs", language)
		}

		if len(vocabulary.Sources) == 0 && vocabulary.Parser == "" {
			t.Errorf("%s was read from no stream and declared by no parser", language)
		}

		if len(vocabulary.Sources) > 0 && len(vocabulary.Bridges) == 0 {
			t.Errorf("%s names %d sources and no bridge that wrote them", language, len(vocabulary.Sources))
		}

		if vocabulary.Parser != "" && vocabulary.Declares == "" {
			t.Errorf("%s names the parser %q and does not say where its declaration was read", language, vocabulary.Parser)
		}

		for _, construct := range vocabulary.Constructs {
			if !construct.Observed() && !construct.Declared() {
				t.Errorf("%s %q is vouched by %v", language, construct.Kind, construct.Vouched)
			}

			if construct.Observed() && (construct.Example == "" || len(construct.Roles) == 0) {
				t.Errorf("%s %q is observed, says it was seen at %q and is in the roles %v", language, construct.Kind, construct.Example, construct.Roles)
			}

			if !construct.Observed() && construct.Example != "" {
				t.Errorf("%s %q was never seen in a stream and names the place %q", language, construct.Kind, construct.Example)
			}
		}
	}
}

func TestEveryObservedConstructIsOneItsParserDeclares(t *testing.T) {
	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	for language, vocabulary := range committed {
		if vocabulary.Parser == "" {
			continue
		}

		declares(t, language, vocabulary)
	}
}

func declares(t *testing.T, language contract.Language, vocabulary Vocabulary) {
	t.Helper()

	known := map[string]bool{}
	for _, field := range vocabulary.Fields() {
		known[field] = true
	}

	for _, construct := range vocabulary.Constructs {
		if !construct.Observed() {
			continue
		}

		if !construct.Declared() {
			t.Errorf("%s writes %q, which %s does not declare: record the declarations again (scripts/dev go run ./contract/declares/record)", language, construct.Kind, vocabulary.Parser)
		}

		for _, field := range construct.Fields {
			if !known[field] {
				t.Errorf("%s writes %q with a child in the slot %q, which %s does not declare", language, construct.Kind, field, vocabulary.Parser)
			}
		}
	}
}

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
		"Expression":               "expression",
		"Statement":                "statement",
	} {
		if got := Spelling(kind); got != spelling {
			t.Errorf("%s spells %q, want %q", kind, got, spelling)
		}
	}
}

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

	committed, err := Vocabularies()
	if err != nil {
		t.Fatalf("the committed vocabularies cannot be read: %v", err)
	}

	for language, vocabulary := range committed {
		written(t, language, vocabulary)
	}
}

func written(t *testing.T, language contract.Language, vocabulary Vocabulary) {
	t.Helper()

	for _, construct := range vocabulary.Constructs {
		if _, collided := vocabulary.Collided[construct.Spells]; collided && construct.Spells != "" {
			t.Errorf("%s writes %q as the spelling of %q, and it collided", language, construct.Spells, construct.Kind)
		}
	}
}
