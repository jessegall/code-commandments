package bridges

import (
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestEveryBridgeOwesTheWholeContract(t *testing.T) {
	if !scored.Load() {
		t.Skip("the score reads what TestEveryBridgeWritesTheExpectedTree observed: run both")
	}
	items := contract.Items()
	for _, language := range slices.Sorted(maps.Keys(bridgeOf)) {
		t.Run(string(language), func(t *testing.T) {
			exempt, err := notOwed(language)
			if err != nil {
				t.Fatal(err)
			}
			observed := witnessed.byLanguage[language]
			var owed, proven int
			var unproven, wrong []string
			for _, item := range items {
				if _, excused := exempt[item]; excused {
					continue
				}
				owed++
				written, seen := observed[item]
				switch {
				case !seen:
					unproven = append(unproven, item)
				case !written:
					wrong = append(wrong, item)
				default:
					proven++
				}
			}
			t.Logf("%s %d/%d (%d%%), %d not owed", language, proven, owed, proven*100/max(owed, 1), len(exempt))
			for _, item := range wrong {
				t.Errorf("%s: written otherwise than its samples expect", item)
			}
			for _, item := range unproven {
				t.Errorf("%s: no sample proves it", item)
			}
		})
	}
}

func notOwed(language contract.Language) (map[string]string, error) {
	text, err := os.ReadFile(filepath.Join("..", "..", "coverage", string(language)+".md"))
	if err != nil {
		return nil, err
	}
	exempt := map[string]string{}
	owing := true
	for _, line := range strings.Split(string(text), "\n") {
		if strings.HasPrefix(line, "## ") {
			owing = strings.TrimSpace(strings.TrimPrefix(line, "## ")) != "Not owed"
		}
		cells := strings.Split(line, "|")
		if owing || len(cells) < 4 {
			continue
		}
		if item := strings.Trim(strings.TrimSpace(cells[1]), "`"); strings.Contains(item, "/") {
			exempt[item] = strings.TrimSpace(cells[2])
		}
	}

	return exempt, nil
}
