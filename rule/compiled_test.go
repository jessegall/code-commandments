package rule

import (
	"strings"
	"testing"
)

func TestOrderAsksTheCheaperOperandFirstAndKeepsACaseAsWritten(t *testing.T) {
	read, err := Parse("probe", written(`"find":{"select":"handler","as":"site","where":["and",["some",["ownDescendants","$site"],"d",["=","$d.kind","throw"]],["exists","$site.body"],["case","$site.kind",{"handler":true},["exists","$site.filter"]]]}`), anySkill)
	if err != nil {
		t.Fatal(err)
	}
	where := *compiled(read.read).Find.Where
	if where.Operands[0].Operator != "exists" || where.Operands[2].Operator != "some" {
		t.Errorf("the conjunction is asked as %s, %s, %s", where.Operands[0].Operator, where.Operands[1].Operator, where.Operands[2].Operator)
	}
	if arms := where.Operands[1]; arms.Operator != "case" || !arms.Operands[1].IsArms() || arms.Operands[2].Operator != "exists" {
		t.Errorf("the case is reordered: %v", arms.Written())
	}
}

func TestOrderTurnedOffAsksTheRuleAsWritten(t *testing.T) {
	t.Setenv(orderingSwitch, "off")
	read, err := Parse("probe", written(`"find":{"select":"handler","as":"site","where":["and",["some",["ownDescendants","$site"],"d",true],["exists","$site.body"]]}`), anySkill)
	if err != nil {
		t.Fatal(err)
	}
	if first := compiled(read.read).Find.Where.Operands[0].Operator; first != "some" {
		t.Errorf("with ordering off the conjunction starts with %s", first)
	}
}

func TestCacheKeyChangesWithAComposedDefinition(t *testing.T) {
	rule := func(threshold string) Rule {
		read, err := Parse("probe", written(`"definitions":{"wide":{"args":["f"],"expr":[">=",["count","$f.caughtTypes"],`+threshold+`]}},"find":{"select":"handler","as":"site","where":["wide","$site"]}`), anySkill)
		if err != nil {
			t.Fatal(err)
		}

		return read
	}
	if rule("2").CacheKey() == rule("3").CacheKey() {
		t.Errorf("editing the definition the rule calls keeps its cache key")
	}
	if rule("2").CacheKey() != rule("2").CacheKey() {
		t.Errorf("the same rule gets two cache keys")
	}
	if !strings.HasPrefix(rule("2").CacheKey(), rule("2").CacheKey()[:8]) {
		t.Errorf("the cache key is not stable")
	}
}
