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

// TestWhichLanguageWritesAConstructIsReadable holds the reading the schema's completion and a later refusal are
// both built on: whether a language a rule judges has been SEEN writing a word, and which languages have. It is a
// reading and not yet a refusal, because the census is observed: `+=` is missing from the TypeScript rows because
// no fixture happens to hold one, and a rule using it would have been refused for being ahead of the census.
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
		// Another language's kind: not seen here, and the rows say whose it is, which is the answer a rule author
		// needs and could not get before.
		{"kind", "InvocationExpression", false, contract.CSharp},
		{"hasModifier", "sealed", false, contract.CSharp},
		{"field", "ReturnType", false, contract.CSharp},
		// A slip of the keyboard is nobody's word.
		{"kind", "static-calls", false, ""},
	} {
		seen, elsewhere := rule.Written(php, each.key, each.named)
		if seen != each.seen {
			t.Errorf("php has been seen writing the %s %q: %v, want %v", each.key, each.named, seen, each.seen)
		}

		if each.elsewhere != "" && !slices.Contains(elsewhere, each.elsewhere) {
			t.Errorf("the %s %q is written by %v, and %s writes it", each.key, each.named, elsewhere, each.elsewhere)
		}

		if each.elsewhere == "" && len(elsewhere) > 0 {
			t.Errorf("the %s %q is written by %v, and it is nobody's word", each.key, each.named, elsewhere)
		}
	}
}
