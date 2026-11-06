package rule_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/rule"
)

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
		`{"select": "function", "where": [{"nameMood": "question"}]}`:                                  "[7 12]",
		`{"select": "function", "where": [{"nameMood": "question"}, {"not": {"returnType": "bool"}}]}`: "[7]",
		`{"select": "function", "where": [{"nameMood": "bare-verb"}]}`:                                 "[17]",
		`{"select": "function", "where": [{"nameMood": "third-person"}]}`:                              "[17 22]",
		`{"select": "function", "where": [{"name": "total"}, {"nameMood": "third-person"}]}`:           "[]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestAMoodThatIsNoMoodSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"nameMood": "imperative"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of bare-verb, third-person, question") {
		t.Errorf("nameMood imperative: %v", err)
	}
}

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
		`{"select": "call", "where": [{"kind": "Expr_StaticCall"}]}`: "[9]",
		`{"select": "call", "where": [{"kind": "static-call"}]}`:     "[9]",
		`{"select": "construct:static-call"}`:                        "[9]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestWhichLanguageWritesAConstructIsReadable(t *testing.T) {
	php := []contract.Language{contract.PHP}

	for _, each := range []struct {
		key       string
		named     string
		seen      bool
		elsewhere contract.Language
	}{
		{"kind", "Expr_StaticCall", true, contract.PHP},
		{"kind", "static-call", true, contract.PHP},
		{"kind", "InvocationExpression", false, contract.CSharp},
		{"field", "ReturnType", false, contract.CSharp},
		{"kind", "static-calls", false, ""},
		{"hasModifier", "sealed", true, contract.CSharp},
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
