package engine_test

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/engine"
)

func TestAFileIsKnownCleanOnlyWhenTheRuleRemembersItWithNoFindings(t *testing.T) {
	memory := engine.Remembering(time.Now)

	tree, _ := treeHolding(t, "one", "two")
	if memory.KnownClean(tree) {
		t.Fatal("files the rule never judged were known clean")
	}

	memory.Find(tree, (&asking{}).find)
	if !memory.KnownClean(tree) {
		t.Error("files the rule judged and found nothing in were not known clean")
	}

	sinful := engine.Remembering(time.Now)
	sinful.Find(tree, (&asking{finds: true}).find)
	if sinful.KnownClean(tree) {
		t.Error("files the rule found something in were known clean")
	}
}
