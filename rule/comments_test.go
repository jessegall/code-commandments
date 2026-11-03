package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

// A comment is no node of the tree — every bridge writes a file's comments beside its nodes — so a rule could
// select nothing to report on one, while three shipped comment rules per language did it through machinery that
// lived in one language's own package. These hold the selector in three languages, and hold it to reporting a
// comment where a reader looks for it: on the code it is about.

// TestARuleSelectsComments holds the selector and the step over what a comment says, in the language whose
// machinery it was lifted from and in two that never had any.
func TestARuleSelectsComments(t *testing.T) {
	php := phpCodebase(t, "Till.php", `<?php

namespace App;

// A note about the class, which the class's own line reports.
final class Till
{
    public function close(): void
    {
        // TODO: take the cash
        $this->send();
    }

    private function send(): void {}
}
`)

	for query, want := range map[string]string{
		// The comment is reported on the line of the code it is about, as a finding about a comment must point at
		// the thing the comment is wrong about.
		`{"select": "comment", "where": [{"commentLike": "*note about the class*"}]}`: "[6]",
		`{"select": "comment", "where": [{"commentLike": "*TODO*"}]}`:                 "[11]",
		`{"select": "comment"}`: "[6 11]",
	} {
		if got := found(t, "backend", query, php); got != want {
			t.Errorf("php %s: found %s, want %s", query, got, want)
		}
	}
}

// TestACommentIsSelectedInEveryLanguage holds it where the comment markers and the trees are another language's,
// since what a rule selects is the comment the bridge wrote and not a token it reads itself.
func TestACommentIsSelectedInEveryLanguage(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `# A note about the module, which has nothing below it but the class.
class Till:
    """What the class is for."""

    def close(self) -> None:
        # TODO: take the cash
        self.send()

    def send(self) -> None:
        pass
`,
	})

	// The module's own note documents the class below it, and the docstring documents the class it sits INSIDE, so
	// both are reported on the class's line: a docstring is attached by its bridge, which is the only way to know
	// what a comment written inside its own subject is about. The docstring is one comment and not two things: a
	// rule selecting comments meets it once, though the string literal is still in the tree.
	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"commentLike": "*note about the module*"}]}`: "[2]",
		`{"select": "comment", "where": [{"commentLike": "*TODO*"}]}`:                  "[7]",
		`{"select": "comment", "where": [{"commentLike": "*What the class is for*"}]}`: "[2]",
		`{"select": "comment"}`: "[2 2 7]",
	} {
		if got := found(t, "python", query, python); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}

	csharp := csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

/// <summary>What the class is for.</summary>
public sealed class Till
{
    public void Close()
    {
        // TODO: take the cash
        Send();
    }

    private void Send() { }
}
`})

	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"commentLike": "*What the class is for*"}]}`: "[4]",
		`{"select": "comment", "where": [{"commentLike": "*TODO*"}]}`:                  "[9]",
	} {
		if got := found(t, "csharp", query, csharp); got != want {
			t.Errorf("csharp %s: found %s, want %s", query, got, want)
		}
	}
}

// The three readings of prose the comment rules ship on are calibrated judgements with their own tests, and a
// project could ask for none of them: a rule could only match words it wrote itself, which is the line this work
// draws. These hold each judgement against the look-alike prose's own tests are built around.

// TestARuleAsksWhatKindOfCommentItIs holds the judgements in PHP, and holds each to leaving alone the comment that
// merely reads like it: a condition stated as a condition is no strawman, and a purpose is no history.
func TestARuleAsksWhatKindOfCommentItIs(t *testing.T) {
	codebase := phpCodebase(t, "Till.php", `<?php

namespace App;

final class Till
{
    // Previously this used the old router, which double-charged the customer.
    public function charge(): void
    {
        // The cache is deliberately not used here, and that is not an oversight.
        $this->send();
    }

    // Sends the slip when the printer is free, since a busy printer drops it.
    public function send(): void
    {
        $this->print();
    }

    private function print(): void {}
}
`)

	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"commentIs": "history"}]}`:  "[8]",
		`{"select": "comment", "where": [{"commentIs": "strawman"}]}`: "[11]",
		// The third comment states why the code does what it does, which is neither.
		`{"select": "comment", "reject": [{"commentIs": "history"}, {"commentIs": "strawman"}]}`: "[15]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestARuleAsksWhetherACommentRestatesTheCode holds the judgement that needs a language's own vocabulary, in two
// languages: a comment saying only what the line spells, against one saying something the line does not.
func TestARuleAsksWhetherACommentRestatesTheCode(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `class Till:
    def close(self, slip: object) -> None:
        # send the slip
        self.send(slip)

        # the printer refuses a slip without a till number
        self.send(slip)

    def send(self, slip: object) -> None:
        pass
`,
	})

	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"commentIs": "restates"}]}`:  "[4]",
		`{"select": "comment", "reject": [{"commentIs": "restates"}]}`: "[7]",
	} {
		if got := found(t, "python", query, python); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}
}

// TestAJudgementNobodyCalibratedSaysWhich holds the closed set, which is the whole point: a rule asks for a
// reading that was measured, never for prose matching words it writes itself.
func TestAJudgementNobodyCalibratedSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "comment", "where": [{"commentIs": "rambling"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of history, strawman, restates") {
		t.Errorf("commentIs rambling: %v", err)
	}
}

// TestARuleAsksWhetherADocCommentNamesCodeThatIsGone holds the reference check with both its exemptions, which are
// what tell it from a broken-link scanner: a reference the compiler could never have resolved, and one whose owner
// is declared outside the scan, are not this project's to answer for.
func TestARuleAsksWhetherADocCommentNamesCodeThatIsGone(t *testing.T) {
	project := csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

/// <summary>Holds the cash, as <see cref="Slip"/> records it.</summary>
public sealed class Till
{
    /// <summary>Closes the till; see <see cref="Till.Vanished"/>.</summary>
    public void Close() { }
}

/// <summary>A slip.</summary>
public sealed class Slip { }
`})

	// The reference to Till.Vanished names a member of a class this project declares, and the class has no such
	// member: that is code that is gone. The reference to Slip resolves, so it is nothing of the kind.
	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"docReference": {"resolves": false}}]}`: "[7]",
		`{"select": "comment", "where": [{"docReference": {"resolves": true}}]}`:  "[4]",
	} {
		if got := found(t, "csharp", query, project); got != want {
			t.Errorf("csharp %s: found %s, want %s", query, got, want)
		}
	}
}

// TestADocReferenceAskingNothingSaysSo holds the one shape the check takes, since a rule file is written by hand
// and a question that asks nothing would quietly match everything.
func TestADocReferenceAskingNothingSaysSo(t *testing.T) {
	written := `{"engine": "csharp", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "comment", "where": [{"docReference": {}}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "asks nothing") {
		t.Errorf("a docReference asking nothing: %v", err)
	}
}
