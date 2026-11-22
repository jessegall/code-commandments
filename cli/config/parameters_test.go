package config

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

func parameterisedRule(t *testing.T) rule.Rule {
	t.Helper()
	read, err := rule.Parse("layered-imports", []byte(`{"$schema":"commandments:neutral-rule/v2","engine":"php",
		"sin":{"name":"layered-imports","aliases":["NamespaceDependencyDetector"],"skill":"shared/dependencies"},
		"parameters":{"minClasses":{"type":"number","default":2,"description":"owners"},"layer":{"type":"list","default":[],"description":"layers"}},
		"find":{"select":"import","as":"i","where":[">=",["count","$param.layer"],"$param.minClasses"]}}`),
		func(string) (skill.Skill, bool) { return nil, true })
	if err != nil {
		t.Fatal(err)
	}

	return read
}

func TestConfigureSetsARuleParameterByItsNameOrAlias(t *testing.T) {
	for _, written := range []string{
		`{"configure": {"layered-imports": [{"minClasses": [3]}, {"layer": [["App\\Ui", ["App\\Domain"]]]}]}}`,
		`{"configure": {"php/NamespaceDependencyDetector": [{"minClasses": [3]}]}}`,
	} {
		project, err := ParsedJSON([]byte(written))
		if err != nil {
			t.Fatal(err)
		}
		tuned, err := tune([]rule.Detector{parameterisedRule(t)}, project.Configurators)
		if err != nil {
			t.Fatalf("%s is refused: %v", written, err)
		}
		if tuned[0].(rule.Rule).CacheKey() == "" {
			t.Errorf("the tuned rule has no cache key")
		}
	}
}

func TestConfigureRefusesAnUnknownParameterNamingRuleAndKey(t *testing.T) {
	project, err := ParsedJSON([]byte(`{"configure": {"layered-imports": [{"minClass": [3]}]}}`))
	if err != nil {
		t.Fatal(err)
	}
	_, err = tune([]rule.Detector{parameterisedRule(t)}, project.Configurators)
	if err == nil || !strings.Contains(err.Error(), "configure(") || !strings.Contains(err.Error(), `"minClass"`) || !strings.Contains(err.Error(), "layered-imports") {
		t.Errorf("an unknown parameter is refused with %v", err)
	}
}
