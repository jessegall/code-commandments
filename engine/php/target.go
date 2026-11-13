package php

import (
	"encoding/json"
	"path"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const cloneWith = "8.5"

var targets = engine.Memoised(func(codebase *engine.Codebase) string {
	for _, file := range codebase.Files() {
		for dir := path.Dir(file.Path); ; dir = path.Dir(dir) {
			if manifest, err := codebase.Read(path.Join(dir, "composer.json")); err == nil {
				var declared struct {
					Require map[string]any `json:"require"`
				}
				if json.Unmarshal(manifest, &declared) != nil {
					return ""
				}
				constraint, _ := declared.Require["php"].(string)

				return lowestVersion(constraint)
			}
			if dir == path.Dir(dir) {
				break
			}
		}
	}

	return ""
})

func SupportsCloneWith(codebase *engine.Codebase) bool {
	lowest := targets.Of(codebase)

	return lowest != "" && compareVersions(lowest, cloneWith) >= 0
}

func lowestVersion(constraint string) string {
	lowest := ""
	for _, clause := range strings.Fields(strings.NewReplacer("||", " ", ",", " ", "|", " ").Replace(constraint)) {
		parts := strings.Split(strings.TrimLeft(strings.TrimSpace(clause), "^~>=< "), ".")
		if _, err := strconv.ParseFloat(parts[0], 64); err != nil {
			continue
		}
		minor := "0"
		if len(parts) > 1 {
			if _, err := strconv.ParseFloat(parts[1], 64); err == nil {
				minor = parts[1]
			}
		}
		if normalised := parts[0] + "." + minor; lowest == "" || compareVersions(normalised, lowest) < 0 {
			lowest = normalised
		}
	}

	return lowest
}

func compareVersions(a, b string) int {
	left, right := strings.Split(a, "."), strings.Split(b, ".")
	for at := range 2 {
		l, _ := strconv.Atoi(left[at])
		r, _ := strconv.Atoi(right[at])
		if l != r {
			return l - r
		}
	}

	return 0
}
