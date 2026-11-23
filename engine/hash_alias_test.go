package engine

import "testing"

func TestANameIsAnAliasOnlyWhereItIsNotTheLastSegmentOfWhatItNames(t *testing.T) {
	for _, each := range []struct {
		name, refers string
		alias        bool
	}{
		{"Wizard", `App\Wizards\AgentDriver`, true},
		{"AgentDriver", `App\Wizards\AgentDriver`, false},
		{"agentdriver", `App\Wizards\AgentDriver`, false},
		{"fmt", "shop.text.fmt", false},
		{"Wizard", "", false},
	} {
		if aliased(each.name, each.refers) != each.alias {
			t.Errorf("%q naming %q reads as an alias: %v", each.name, each.refers, !each.alias)
		}
	}
}
