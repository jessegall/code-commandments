package rule_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/rule"
)

// The ONE check in the catalogue that reads a name, which is only justified where the sin IS the name. It answers
// whether a name READS a certain way and nothing more, so a rule pairs it with what the member actually does: the
// mood alone would flag the predicate that is MEANT to read as a question, which is the look-alike here.

// TestARuleAsksWhatMoodANameIsIn holds the three moods, and holds the check to being half a rule: the question
// mood keeps the predicate and the command alike, and only what the member answers tells them apart.
func TestARuleAsksWhatMoodANameIsIn(t *testing.T) {
	codebase := phpCodebase(t, "Till.php", `<?php

namespace App;

final class Till
{
    public function isSendable(Slip $slip): void
    {
        $slip->send();
    }

    public function isPaid(Slip $slip): bool
    {
        return $slip->paid();
    }

    public function holds(): bool
    {
        return true;
    }

    public function entersTestMode(): void
    {
    }

    public function total(): int
    {
        return 1;
    }
}
`)

	for query, want := range map[string]string{
		// Both names open with a question word, which is all the mood says.
		`{"select": "function", "where": [{"nameMood": "question"}]}`: "[7 12]",
		// What makes one of them a sin is the mood against what it does: a question that answers nothing is a
		// command wearing a question's name, and that is a rule a project can now write.
		`{"select": "function", "where": [{"nameMood": "question"}, {"not": {"returnType": "bool"}}]}`: "[7]",
		// The verb and nothing else, against a verb that narrates what it does.
		`{"select": "function", "where": [{"nameMood": "bare-verb"}]}`:    "[17]",
		`{"select": "function", "where": [{"nameMood": "third-person"}]}`: "[17 22]",
		// A noun is in no mood at all.
		`{"select": "function", "where": [{"name": "total"}, {"nameMood": "third-person"}]}`: "[]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestAMoodThatIsNoMoodSaysWhich holds the closed set, which is what keeps this check from becoming a word list a
// rule writes for itself.
func TestAMoodThatIsNoMoodSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"nameMood": "imperative"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of bare-verb, third-person, question") {
		t.Errorf("nameMood imperative: %v", err)
	}
}

// A kind is the language's own word, which is the truth and unreadable: `Expr_StaticCall` is nobody's idea of a
// rule. The vocabulary derives a spelling for every kind, so a rule writes either and means the same construct.

// TestARuleWritesAKindAsTheVocabularySpellsIt holds both spellings naming one construct, and holds a select by
// spelling beside a select by neutral kind.
func TestARuleWritesAKindAsTheVocabularySpellsIt(t *testing.T) {
	codebase := phpCodebase(t, "Till.php", `<?php

namespace App;

final class Till
{
    public function close(): void
    {
        Slip::of(1);
        $this->send();
    }

    private function send(): void {}
}
`)

	for query, want := range map[string]string{
		// The language's own word and the spelling derived from it are one construct.
		`{"select": "call", "where": [{"kind": "Expr_StaticCall"}]}`: "[9]",
		`{"select": "call", "where": [{"kind": "static-call"}]}`:     "[9]",
		// And a select by spelling reaches the construct with no neutral kind standing for it.
		`{"select": "construct:static-call"}`: "[9]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestWhichLanguageWritesAConstructIsReadable holds the reading the schema's completion and the rule refusal are
// both built on: whether a language a rule judges writes a word, and which languages write it anywhere.
//
// It answers per KEY, and that is the whole of what makes it safe to refuse on. A parser answers for the kinds it
// can emit and most of them for the slots a child fills, so for those keys the rows are the whole of what the
// language has and a word outside them is nobody's. No parser answers for the operators, flags and modifiers a
// node carries, because nothing asked them to, so for those keys the rows are a census again: `+=` is missing from
// the TypeScript operators because no fixture holds one, and a rule using it must not be refused for being ahead
// of a sample. Whose word it is is read from every language's rows either way, since naming the owner is a hint
// and never a refusal.
func TestWhichLanguageWritesAConstructIsReadable(t *testing.T) {
	php := []contract.Language{contract.PHP}

	for _, each := range []struct {
		key       string
		named     string
		seen      bool
		elsewhere contract.Language
	}{
		{"kind", "Expr_StaticCall", true, contract.PHP},
		// The spelling derived from a kind names it too.
		{"kind", "static-call", true, contract.PHP},
		// Another language's kind: php's parser declares its kinds, so this is not php's and the rows say whose it
		// is, which is the answer a rule author needs and could not get before.
		{"kind", "InvocationExpression", false, contract.CSharp},
		// A field, which php's parser also declares, so the same refusal holds.
		{"field", "ReturnType", false, contract.CSharp},
		// A slip of the keyboard is nobody's word.
		{"kind", "static-calls", false, ""},
		// But a MODIFIER is offered, because no parser was ever asked which modifiers it writes: php's rows hold
		// only what a fixture showed, so a modifier missing from them may still be one php writes. It is still
		// known as C#'s, which is the hint without the refusal.
		{"hasModifier", "sealed", true, contract.CSharp},
		// And so is an operator nobody declared, however little it looks like php's.
		{"operator", "??=", true, ""},
	} {
		seen, elsewhere := rule.Written(php, each.key, each.named)
		if seen != each.seen {
			t.Errorf("php writes the %s %q: %v, want %v", each.key, each.named, seen, each.seen)
		}

		if each.elsewhere != "" && !slices.Contains(elsewhere, each.elsewhere) {
			t.Errorf("the %s %q is written by %v, and %s writes it", each.key, each.named, elsewhere, each.elsewhere)
		}
	}
}

// TestARuleNamingAConstructNoParserWritesIsRefused holds the refusal the declared vocabulary made possible, and
// the three answers it owes a rule author. Before this, such a rule parsed, ran and matched nothing, which reads
// as a rule that found no sins: the worst way for a tool like this to be wrong.
func TestARuleNamingAConstructNoParserWritesIsRefused(t *testing.T) {
	for _, each := range []struct {
		why   string
		find  string
		said  string
		stops bool
	}{
		{
			why:   "a typo is nobody's word, and the nearest real one is offered",
			find:  `{"select": "kind:Expr_StaticCalls"}`,
			said:  "Expr_StaticCalls",
			stops: true,
		},
		{
			why:   "another language's kind is named as that language's, since a rule author reaching for it has the wrong engine, not a typo",
			find:  `{"select": "function", "where": [{"kind": "InvocationExpression"}]}`,
			said:  "csharp",
			stops: true,
		},
		{
			why:   "a nested step is read too, since a rule hides most of its words below its first one",
			find:  `{"select": "function", "where": [{"child": {"kind": "ClassDeclaration"}}]}`,
			said:  "ClassDeclaration",
			stops: true,
		},
		{
			why:   "a modifier no parser was asked about is left alone, so a rule is never refused for being ahead of a census",
			find:  `{"select": "function", "where": [{"hasModifier": "sealed"}]}`,
			stops: false,
		},
		{
			why:   "and the construct php really writes passes, by its own word or by its spelling",
			find:  `{"select": "construct:static-call"}`,
			stops: false,
		},
	} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": ` + each.find + `}`
		_, err := rule.Parse("X", []byte(written), shipped)

		switch {
		case each.stops && err == nil:
			t.Errorf("%s: the rule parsed", each.why)
		case each.stops && !strings.Contains(err.Error(), each.said):
			t.Errorf("%s: refused with %v, which does not name %q", each.why, err, each.said)
		case !each.stops && err != nil:
			t.Errorf("%s: %v", each.why, err)
		}
	}
}
