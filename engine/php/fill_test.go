package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestPHPFillsWhereverTheCodebaseIsBuilt(t *testing.T) {
	codebase := shop.Project(t)

	resolved, targeted := 0, 0

	for _, file := range codebase.Of(contract.PHP).Files() {
		for node := range file.Nodes() {
			if node.Resolved != nil {
				resolved++
			}

			if node.Target != nil {
				targeted++
			}
		}
	}

	if resolved == 0 {
		t.Error("no PHP expression carries a resolved type, so nothing filled them where this codebase was built")
	}

	if targeted == 0 {
		t.Error("no PHP call carries a target, so nothing filled them where this codebase was built")
	}
}
