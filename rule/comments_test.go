package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

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
		`{"select": "comment", "where": [{"commentLike": "*note about the class*"}]}`: "[6]",
		`{"select": "comment", "where": [{"commentLike": "*TODO*"}]}`:                 "[11]",
		`{"select": "comment"}`: "[6 11]",
	} {
		if got := found(t, "backend", query, php); got != want {
			t.Errorf("php %s: found %s, want %s", query, got, want)
		}
	}
}

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
		`{"select": "comment", "where": [{"commentIs": "history"}]}`:                             "[8]",
		`{"select": "comment", "where": [{"commentIs": "strawman"}]}`:                            "[11]",
		`{"select": "comment", "reject": [{"commentIs": "history"}, {"commentIs": "strawman"}]}`: "[15]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

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

func TestAJudgementNobodyCalibratedSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "comment", "where": [{"commentIs": "rambling"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of history, strawman, restates") {
		t.Errorf("commentIs rambling: %v", err)
	}
}

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

	for query, want := range map[string]string{
		`{"select": "comment", "where": [{"docReference": {"resolves": false}}]}`: "[7]",
		`{"select": "comment", "where": [{"docReference": {"resolves": true}}]}`:  "[4]",
	} {
		if got := found(t, "csharp", query, project); got != want {
			t.Errorf("csharp %s: found %s, want %s", query, got, want)
		}
	}
}

func TestADocReferenceAskingNothingSaysSo(t *testing.T) {
	written := `{"engine": "csharp", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "comment", "where": [{"docReference": {}}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "asks nothing") {
		t.Errorf("a docReference asking nothing: %v", err)
	}
}
