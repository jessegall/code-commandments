package config

import (
	"os"
	"path/filepath"
	"reflect"
	"testing"
)

func TestARuleJudgesTheFolderItsConfigNames(t *testing.T) {
	config := configWith(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {
            "custom/TestsDriveTheRealPath": [{"judges": ["tests"]}],
            "vue/ComponentBudgetDetector": [{"elements": [40]}]
        }
    }`)

	if judged := config.Judged(RuleNamed(Detector, "custom/TestsDriveTheRealPath")); !reflect.DeepEqual(judged, []string{"tests"}) {
		t.Errorf("the rule judges %v, want [tests]", judged)
	}
	if judged := config.Judged(RuleNamed(Detector, "vue/ComponentBudgetDetector")); len(judged) != 0 {
		t.Errorf("a rule that was given no folder judges %v", judged)
	}
	if judged := config.JudgedDespiteExclusion(); !reflect.DeepEqual(judged, []string{"tests"}) {
		t.Errorf("the scan reads %v despite the exclusion, want [tests]", judged)
	}
}

func TestAJudgesCallIsNeverSentToTheDetector(t *testing.T) {
	config := configWith(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}
    }`)

	if _, err := config.Enabled(Everything); err != nil {
		t.Errorf("a judges call under a rule the shipped list does not hold is refused: %v", err)
	}
}

func TestTheOtherConfigureCallsStillReachTheirDetector(t *testing.T) {
	config := configWith(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"vue/ComponentBudgetDetector": [{"judges": ["tests"]}, {"elements": [40]}]}
    }`)

	if _, err := config.Enabled(Everything); err != nil {
		t.Errorf("the method beside the judges call no longer reaches its detector: %v", err)
	}

	broken := configWith(t, `{
        "paths": ["src"],
        "configure": {"vue/ComponentBudgetDetector": [{"noSuchMethod": [1]}]}
    }`)

	if _, err := broken.Enabled(Everything); err == nil {
		t.Error("a configure call naming no method of the detector is accepted")
	}
}

func TestAFolderIsNamedWhicheverWayTheConfigWritesIt(t *testing.T) {
	for name, given := range map[string]string{
		"one folder":            `[{"judges": ["tests"]}]`,
		"several folders":       `[{"judges": ["tests", "spec"]}]`,
		"a list of folders":     `[{"judges": [["tests", "spec"]]}]`,
		"slashes around it":     `[{"judges": ["/tests/"]}]`,
		"a call per folder":     `[{"judges": ["tests"]}, {"judges": ["spec"]}]`,
		"the same folder twice": `[{"judges": ["tests"]}, {"judges": ["tests"]}]`,
	} {
		t.Run(name, func(t *testing.T) {
			config := configWith(t, `{"paths": ["src"], "configure": {"custom/R": `+given+`}}`)

			judged := config.Judged(RuleNamed(Detector, "custom/R"))
			if len(judged) == 0 || judged[0] != "tests" {
				t.Fatalf("the rule judges %v, want tests first", judged)
			}
			for at, folder := range judged {
				if folder == "" {
					t.Errorf("folder %d is empty", at)
				}
			}
		})
	}
}

func configWith(t *testing.T, text string) Config {
	t.Helper()
	root := t.TempDir()
	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".commandments", "config.json"), []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}

	config, err := Load(root)
	if err != nil {
		t.Fatalf("the config does not load: %v", err)
	}

	return config
}
