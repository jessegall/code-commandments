package rule_test

import (
	"strings"
	"testing"

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
