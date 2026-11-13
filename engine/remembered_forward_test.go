package engine_test

import (
	"os"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func TestARuleReadingForwardAsksAgainOnlyTheFileThatChanged(t *testing.T) {
	for name, want := range map[string]int{"forward": 1, "across": 2} {
		memory := engine.Remembering(time.Now)
		detector := &asking{finds: true}
		find := memory.FindAcross
		if name == "forward" {
			find = memory.FindForward
		}

		tree, _ := treeHolding(t, "one", "two")
		find(tree, detector.find)
		detector.asked = 0

		tree, _ = treeHolding(t, "one", "two, its body edited")
		find(tree, detector.find)

		if detector.asked != want {
			t.Errorf("%s: after one file's body changed the rule was asked about %d files, want %d", name, detector.asked, want)
		}
	}
}

// A TypeScript symbol is named by its module's absolute path, so a worktree names every symbol differently from the
// checkout it was cut from; a rule reading forward still knows the same files there.
func TestARuleReadingForwardKnowsTheSameFilesInAnotherCheckout(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}

	memory.FindForward(checkoutAt(t, "/project"), detector.find)
	asked := detector.asked
	memory.FindForward(checkoutAt(t, "/project/.journal/worktrees/worker-1"), detector.find)

	if asked == 0 || detector.asked != asked {
		t.Errorf("the worktree's files of the same contents asked the rule again: %d files after %d", detector.asked, asked)
	}
}

// checkoutAt is the PHP sample as a checkout at root holds it: every path moved there, and every symbol named by its
// file's path the way the TypeScript bridge names one.
func checkoutAt(t *testing.T, root string) *engine.Codebase {
	t.Helper()
	sampled, err := os.Open("../contract/samples/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer sampled.Close()
	stream, err := contract.ReadAll(sampled)
	if err != nil {
		t.Fatal(err)
	}

	moved := func(path string) string { return root + strings.TrimPrefix(path, "/fixtures") }
	for at, held := range stream.Header.Roots {
		stream.Header.Roots[at] = moved(held)
	}
	var named func(node *contract.Node, path string)
	named = func(node *contract.Node, path string) {
		if node.Facts != nil && node.Symbol != "" {
			node.Symbol = `"` + path + `".` + node.Symbol
		}
		for _, child := range node.Children {
			named(child, path)
		}
	}
	for _, file := range stream.Files {
		file.Path = moved(file.Path)
		file.Reads = []string{}
		named(file.Root, file.Path)
	}

	return engine.New(func(path string) ([]byte, error) {
		return []byte(strings.TrimPrefix(path, root) + strings.Repeat(" ", 1<<20)), nil
	}, stream)
}
