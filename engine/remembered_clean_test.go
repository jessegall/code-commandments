package engine_test

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cache"
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

// A rule answered from its clean answers alone uses them as much as one that asks each file, so the answers stay.
func TestAKnownCleanHitRenewsItsAnswers(t *testing.T) {
	now := time.Now()
	memory := engine.Remembering(func() time.Time { return now })
	tree, _ := treeHolding(t, "used")
	memory.Find(tree, (&asking{}).find)

	now = now.Add(cache.KeptFor() - time.Hour)
	memory.KnownClean(tree)
	now = now.Add(cache.KeptFor() - time.Hour)

	if !memory.KnownClean(tree) {
		t.Error("answers used by a known-clean hit a day ago were dropped as unused")
	}
}
