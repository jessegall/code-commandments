package php

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheCommittedShopIsGeneratedFromTodaysSources(t *testing.T) {
	committed, err := os.ReadFile(filepath.Join(shop.Testdata(), "shop.digest"))
	if err != nil {
		t.Fatal(err)
	}
	digest, err := shop.Digest()
	if err != nil {
		t.Fatal(err)
	}
	if strings.TrimSpace(string(committed)) != digest {
		t.Fatal("the shop fixture, src/Ast, bridge/php or the oracle changed since engine/php/testdata was generated: run go generate ./engine/php")
	}
}

func TestTheGoTreeIsThePhpEnginesTree(t *testing.T) {
	shop.Parity(t, "parents", func(_ shop.Answer, node engine.Match) any {
		return node.Parent().Kind()
	})
}

func TestTheGoTreeHoldsNoNodeThePhpEngineLacks(t *testing.T) {
	nodes := 0
	for _, file := range shop.Codebase(t).Files() {
		nodes += len(file.Nodes()) - 1
	}
	if answers := len(shop.Answers(t, "parents")); nodes != answers {
		t.Fatalf("the Go tree holds %d nodes under its file roots, the PHP engine %d", nodes, answers)
	}
}
