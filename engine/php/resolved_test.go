package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func resolvedAt(t *testing.T, codebase *engine.Codebase, written string) engine.Match {
	t.Helper()
	for _, node := range codebase.Where(func(node engine.Match) bool { return node.Written() == written }).Get() {
		if node.Role() == "expression" {
			return node
		}
	}

	t.Fatalf("no expression written %q in the source", written)

	return engine.Match{}
}

func TestAValueTheTypeEngineCannotReadCarriesNoType(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Counter
{
    public function count($rows)
    {
        $total = $rows;

        return $total;
    }
}
`)

	for _, written := range []string{"$rows", "$total"} {
		if resolved := resolvedAt(t, codebase, written).Node().Resolved; resolved != nil {
			t.Errorf("%s carries the type %q, and nothing says what it holds", written, resolved.Text)
		}
	}
}
