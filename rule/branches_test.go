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

	for name, want := range map[string]struct {
		check  string
		fires  string
		leaves string
	}{
		"a redundant else":          {`{"redundantElse": true}`, "redundant", "decided"},
		"a buried guard":            {`{"soleLoopBodyGuard": true}`, "buried", "picked"},
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

	passing := `{"select": "loop", "where": [{"passesItsElementOn": true}]}`
	if found := foundAt(t, "backend", passing, project); found == "[]" {
		t.Error("no loop reads as handing its item on, and both of them do")
	}
}

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
		"a buried guard":   {`{"soleLoopBodyGuard": true}`, "Buried", "Bound"},
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
