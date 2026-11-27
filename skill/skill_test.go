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

func TestTheOpeningReadsTheSameFromARenderedLessonAsFromItsBody(t *testing.T) {
	body := "> One home for one piece of knowledge.\n> Second line.\n> Read this before you paste.\n\n## The principle\nWrite it once.\n\n## The sins\n"
	rendered := "---\nname: commandments-shared-x\ndescription: \"x\"\n---\n\n# X\n\n> 🔱 **Load `fix-at-the-source` first.** The rule above all.\n\n" + body
	if skill.OpeningOf(rendered) != skill.OpeningOf(body) || skill.OpeningOf(body) != "One home for one piece of knowledge. Second line." {
		t.Errorf("opening from the rendered lesson %q, from its body %q", skill.OpeningOf(rendered), skill.OpeningOf(body))
	}
	if skill.PrincipleOf(rendered) != "Write it once." {
		t.Errorf("principle from the rendered lesson %q", skill.PrincipleOf(rendered))
	}
}
