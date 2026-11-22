package rules_test

import (
	"encoding/json"
	"fmt"
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
)

// shippedSin is what the manifest reads of one rule file: the sin it names, and the file it is in.
type shippedSin struct {
	file    string
	name    string
	aliases []string
	skill   string
	engines []string
}

func shippedSins(t *testing.T) []shippedSin {
	t.Helper()
	var shipped []shippedSin
	for _, source := range rules.Sources() {
		var read struct {
			Engine json.RawMessage `json:"engine"`
			Sin    *struct {
				Name    string   `json:"name"`
				Aliases []string `json:"aliases"`
				Skill   string   `json:"skill"`
			} `json:"sin"`
		}
		if err := json.Unmarshal(source.Text, &read); err != nil {
			t.Fatalf("%s: %v", source.Path, err)
		}
		if read.Sin == nil {
			continue
		}
		var engines []string
		if json.Unmarshal(read.Engine, &engines) != nil {
			var one string
			_ = json.Unmarshal(read.Engine, &one)
			engines = []string{one}
		}
		shipped = append(shipped, shippedSin{source.Path, read.Sin.Name, read.Sin.Aliases, read.Sin.Skill, engines})
	}

	return shipped
}

func TestManifestEveryShippedNameReachesOneRuleFile(t *testing.T) {
	if problems := rules.Problems(); len(problems) != 0 {
		t.Fatalf("the catalogue holds rules the loader refuses, a composed reference among them: %v", problems)
	}
	shipped := shippedSins(t)
	fileOf := map[string]string{}
	for _, each := range shipped {
		if other, taken := fileOf[each.name]; taken {
			t.Errorf("the sin %s is named by %s and %s", each.name, other, each.file)
		}
		fileOf[each.name] = each.file
	}
	for _, each := range shipped {
		for _, engine := range each.engines {
			for _, name := range append([]string{each.name}, each.aliases...) {
				var reached []string
				for _, found := range rule.EveryNamedIn(rule.All(), engine+"/"+name) {
					if canonical := found.Sin().Definition().Name; !slices.Contains(reached, canonical) {
						reached = append(reached, canonical)
					}
				}
				if !slices.Equal(reached, []string{each.name}) && !(len(reached) == 0 && isUnpublished(t, each.file)) {
					t.Errorf("%s/%s (of %s) reaches %v, not the one rule %s", engine, name, each.file, reached, each.name)
				}
			}
		}
	}
}

func TestManifestEveryRegisteredRuleIsAJSONRule(t *testing.T) {
	for _, registered := range rule.All() {
		if _, isFile := registered.(rule.Rule); !isFile {
			t.Errorf("%s is registered and is no rule file: %T", catalog.Name(registered), registered)
		}
	}
}

// TestManifestIsWrittenForCalibration writes one line per shipped rule where COMMANDMENTS_MANIFEST names a file.
func TestManifestIsWrittenForCalibration(t *testing.T) {
	out := os.Getenv("COMMANDMENTS_MANIFEST")
	if out == "" {
		t.Skip("COMMANDMENTS_MANIFEST names no file to write the manifest to")
	}
	shipped := shippedSins(t)
	lines := []string{fmt.Sprintf("# %d rules: name, aliases, skill, engines, file", len(shipped))}
	for _, each := range shipped {
		lines = append(lines, strings.Join([]string{each.name, strings.Join(each.aliases, ","), each.skill, strings.Join(each.engines, ","), each.file}, "\t"))
	}
	if err := os.WriteFile(out, []byte(strings.Join(lines, "\n")+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}
}

func isUnpublished(t *testing.T, file string) bool {
	t.Helper()
	for _, source := range rules.Sources() {
		if source.Path == file {
			return strings.Contains(string(source.Text), `"unpublished": true`)
		}
	}

	return false
}
