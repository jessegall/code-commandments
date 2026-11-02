package rule_test

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Four readings the engine has made for years, for shipped rules in four languages each, and which a project could
// not ask for: a project could not write the rule our own catalogue teaches it. Each is now a check, and each is
// held here beside the look-alike it must refuse, since a reading that fires on the shape alone would be worse
// than none.

// TestARuleAsksForTheReadingsTheEngineAlreadyMakes holds the three branch readings and the loop one over PHP, each
// with the shape it must leave alone in the same file.
func TestARuleAsksForTheReadingsTheEngineAlreadyMakes(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Shipping.php": `<?php

namespace App;

enum State: string
{
    case New = 'new';
    case Paid = 'paid';
    case Sent = 'sent';
}

class Shipping
{
    public function redundant(Order $order): string
    {
        if ($order->isEmpty()) {
            return 'nothing';
        } else {
            $this->log($order);

            return 'something';
        }
    }

    public function decided(Order $order): string
    {
        if ($order->isEmpty()) {
            $this->log($order);

            return 'nothing';
        }

        return 'something';
    }

    public function buried(array $lines): void
    {
        foreach ($lines as $line) {
            if ($line->isReal()) {
                $this->log($line);
                $this->send($line);
            }
        }
    }

    public function picked(array $lines): void
    {
        foreach ($lines as $line) {
            if ($line->isReal()) {
                $this->log($line);

                return;
            }
        }
    }

    public function laddered(State $state): string
    {
        if ($state === State::New) {
            return 'new';
        } elseif ($state === State::Paid) {
            return 'paid';
        } elseif ($state === State::Sent) {
            return 'sent';
        }

        return 'unknown';
    }

    public function unrelated(Order $order, State $state): string
    {
        if ($order->isEmpty()) {
            return 'empty';
        } elseif ($state === State::Paid) {
            return 'paid';
        } elseif ($order->isLate()) {
            return 'late';
        }

        return 'unknown';
    }

    private function log(object $thing): void {}

    private function send(object $thing): void {}
}
`,
	})

	// The method each reading is asked about, rather than its line, since what matters is that the reading fires
	// on the shape and not on the look-alike beside it.
	for name, want := range map[string]struct {
		check  string
		fires  string
		leaves string
	}{
		// The else stands though the taken path already left; the branch that decides between two real paths does
		// not, since nothing above its else leaves.
		"a redundant else": {`{"redundantElse": true}`, "redundant", "decided"},
		// The whole body of a loop, burying two statements; the one that ends by leaving is picking an item.
		"a buried guard": {`{"soleLoopBodyGuard": true}`, "buried", "picked"},
		// Three rungs over one subject; the chain of unrelated tests has rungs and no subject, so it is no ladder.
		"a ladder over one subject": {`{"ladderRungs": {"atLeast": 3}}`, "laddered", "unrelated"},
	} {
		t.Run(name, func(t *testing.T) {
			fires := `{"select": "branch", "where": [` + want.check + `, {"of": "enclosingFunction", "name": "` + want.fires + `"}]}`
			if found := foundAt(t, "backend", fires, project); found == "[]" {
				t.Errorf("%s does not fire on %s", want.check, want.fires)
			}

			leaves := `{"select": "branch", "where": [` + want.check + `, {"of": "enclosingFunction", "name": "` + want.leaves + `"}]}`
			if found := foundAt(t, "backend", leaves, project); found != "[]" {
				t.Errorf("%s fires on %s, which it must leave alone: %s", want.check, want.leaves, found)
			}
		})
	}

	// A loop that hands its item on, and the engine's own pardon: both loops here pass their line to something
	// else, which is what the walk-the-collection rules share.
	passing := `{"select": "loop", "where": [{"passesItsElementOn": true}]}`
	if found := foundAt(t, "backend", passing, project); found == "[]" {
		t.Error("no loop reads as handing its item on, and both of them do")
	}
}

// TestTheSameReadingsAnswerForGo holds them in a second language, since the whole point of these four is that one
// reading serves every language the engine knows: the neutral kinds say what a branch and an exit are, and each
// grammar says where its own tree files the taken path, the else and the item a loop binds.
func TestTheSameReadingsAnswerForGo(t *testing.T) {
	project := goProject(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"shop/shipping.go": `package shop

// Redundant carries an else though its taken path already left.
func Redundant(empty bool, logged *int) string {
	if empty {
		return "nothing"
	} else {
		*logged++

		return "something"
	}
}

// Buried hides two statements behind a condition that is the loop's whole body.
func Buried(lines []int, logged *int) {
	for _, line := range lines {
		if line > 0 {
			*logged++
			*logged += line
		}
	}
}

// Bound binds a name for its own body, which a guard would widen, so it is pardoned.
func Bound(lines map[string]int, logged *int) {
	for key := range lines {
		if line, ok := lines[key]; ok {
			*logged++
			*logged += line
		}
	}
}
`,
	})

	for name, want := range map[string]struct {
		check  string
		fires  string
		leaves string
	}{
		"a redundant else": {`{"redundantElse": true}`, "Redundant", ""},
		// Bound binds a name for its own body, which inverting the guard would widen, so the reading pardons it.
		"a buried guard": {`{"soleLoopBodyGuard": true}`, "Buried", "Bound"},
	} {
		t.Run(name, func(t *testing.T) {
			fires := `{"select": "branch", "where": [` + want.check + `, {"of": "enclosingFunction", "name": "` + want.fires + `"}]}`
			if found := foundAt(t, "go", fires, project); found == "[]" {
				t.Errorf("%s does not fire on %s", want.check, want.fires)
			}

			if want.leaves == "" {
				return
			}

			leaves := `{"select": "branch", "where": [` + want.check + `, {"of": "enclosingFunction", "name": "` + want.leaves + `"}]}`
			if found := foundAt(t, "go", leaves, project); found != "[]" {
				t.Errorf("%s fires on %s, which it must leave alone: %s", want.check, want.leaves, found)
			}
		})
	}
}

// goProject is the codebase of those Go sources, read by the Go bridge as any run reads it.
func goProject(t *testing.T, files map[string]string) *engine.Codebase {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the stream breaks the contract: %v", err)
	}

	return engine.Load(stream)
}
