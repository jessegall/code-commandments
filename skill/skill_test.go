package skill_test

import (
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
)

// TestASlugNamesItsSkillUnderTheEngineItWasPublishedUnder holds a slug a project wrote before the engines were
// named after their languages: a custom rule or a config that says `backend/absence` still means the PHP engine's
// absence skill, and always will.
func TestASlugNamesItsSkillUnderTheEngineItWasPublishedUnder(t *testing.T) {
	for _, slug := range []string{"php/absence", "backend/absence"} {
		found, known := skill.Slugged(slug)
		if !known {
			t.Fatalf("%s names no skill", slug)
		}

		if found.Definition().Slug != "php/absence" {
			t.Errorf("%s names %s", slug, found.Definition().Slug)
		}
	}

	if _, known := skill.Slugged("backend/no-such-subject"); known {
		t.Error("a former engine name does not make up a subject that is not there")
	}
}
