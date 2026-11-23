package skill_test

import (
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
)

func TestASlugNamesItsSkillUnderTheEngineItWasPublishedUnder(t *testing.T) {
	for slug, names := range map[string]string{
		"php/spatie-data":     "php/spatie-data",
		"backend/spatie-data": "php/spatie-data",
		"php/absence":         "shared/absence",
		"backend/absence":     "shared/absence",
		"python/flow":         "shared/flow",
	} {
		found, known := skill.Slugged(slug)
		if !known {
			t.Fatalf("%s names no skill", slug)
		}

		if found.Definition().Slug != names {
			t.Errorf("%s names %s, where it names %s: a skill that took the place of others is found by each of their slugs", slug, found.Definition().Slug, names)
		}
	}

	if _, known := skill.Slugged("backend/no-such-subject"); known {
		t.Error("a former engine name does not make up a subject that is not there")
	}
}
