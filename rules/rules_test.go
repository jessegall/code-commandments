package rules_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
)

func TestInstalledRulesAllLoad(t *testing.T) {
	if problems := rules.Problems(); len(problems) != 0 {
		t.Errorf("installed rules the loader refused: %v", problems)
	}
	if len(rules.Sources()) < 3 {
		t.Errorf("the catalogue holds %d rules", len(rules.Sources()))
	}
}

func TestInstalledRuleIsRegisteredUnderItsNameAliasesAndSkill(t *testing.T) {
	for _, detector := range rule.All() {
		sin := detector.Sin().Definition()
		if sin.Name != "namespace-cycle" {
			continue
		}
		if !slices.Contains(sin.Formerly, "python-namespace-cycle") || sin.Skill == nil || sin.Skill.Definition().Slug != "shared/dependencies" {
			t.Errorf("namespace-cycle is registered with aliases %v and skill %v", sin.Formerly, sin.Skill)
		}

		return
	}
	t.Errorf("no detector is registered for namespace-cycle")
}

func TestOverrideFileReplacesTheEmbeddedRuleAndRemovingItRestoresIt(t *testing.T) {
	folder := t.TempDir()
	written, err := rules.Export(folder)
	if err != nil || written < 3 {
		t.Fatalf("export wrote %d files: %v", written, err)
	}
	key := func(library rule.Library) string {
		for _, each := range library.Rules {
			if each.Sin().Definition().Name == "data-clump" {
				return each.CacheKey()
			}
		}

		return ""
	}
	embedded := key(rules.LoadFrom(""))
	if exported := key(rules.LoadFrom(folder)); exported != embedded {
		t.Errorf("the exported catalogue reads otherwise than the embedded one")
	}
	path := filepath.Join(folder, "shared-value-objects", "data-clump.json")
	text, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	var edited map[string]any
	if err := json.Unmarshal(text, &edited); err != nil {
		t.Fatal(err)
	}
	pipeline := edited["find"].(map[string]any)["pipeline"].([]any)
	pipeline[2].(map[string]any)["where"].([]any)[2] = 4
	rewritten, _ := json.Marshal(edited)
	if err := os.WriteFile(path, rewritten, 0o644); err != nil {
		t.Fatal(err)
	}
	if changed := key(rules.LoadFrom(folder)); changed == embedded || changed == "" {
		t.Errorf("the edited override does not change the rule")
	}
	if err := os.Remove(path); err != nil {
		t.Fatal(err)
	}
	if restored := key(rules.LoadFrom(folder)); restored != embedded {
		t.Errorf("removing the override does not restore the embedded rule")
	}
}

func TestAnInstalledRulesFolderIsTheWholeCatalogueSoARemovedRuleIsGone(t *testing.T) {
	folder := t.TempDir()
	if _, err := rules.Export(folder); err != nil {
		t.Fatal(err)
	}
	named := func(library rule.Library) bool {
		return slices.ContainsFunc(library.Rules, func(each rule.Rule) bool { return each.Sin().Definition().Name == "data-clump" })
	}
	if !named(rules.LoadInstalled(folder)) {
		t.Fatalf("the installed folder does not hold data-clump before it is removed")
	}
	if err := os.Remove(filepath.Join(folder, "shared-value-objects", "data-clump.json")); err != nil {
		t.Fatal(err)
	}
	if named(rules.LoadInstalled(folder)) {
		t.Errorf("a rule removed from the installed folder is still read from the embedded catalogue")
	}
	if !named(rules.LoadInstalled(t.TempDir())) {
		t.Errorf("a folder that holds no catalogue does not fall back to the embedded rules")
	}
}
