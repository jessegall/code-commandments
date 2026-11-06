package custom

import (
	"strings"
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
)

const claimingSkill = "---\n" +
	"name: No raw SQL\n" +
	"description: Reach for this before you write a query.\n" +
	"---\n\n" +
	"# No raw SQL\n\n" +
	"Load `commandments-php-fix-at-the-source` first, and keep `.commandments/custom/lists.json` current.\n\n" +
	"## When it fires\n\n" +
	"- **`raw-sql`** - a query written at a call site.\n" +
	"- **`docstring-in-method`** - a docstring inside a method.\n" +
	"- `field-docstring` - a docstring under a field.\n" +
	"- `python-bloated-docblock` - the shipped rule, which is declared.\n\n" +
	"## The fix\n\n" +
	"Send it through the repository, never `like-this` at a call site.\n"

func TestASkillIsToldWhichRulesItNamesAreDeclaredNowhere(t *testing.T) {
	loaded := Load(project(t, map[string]string{
		"RawSqlDetector.json":        rawSQL,
		"skills/no-raw-sql/SKILL.md": claimingSkill,
	}))

	warnings := loaded.ClaimWarnings()
	if len(warnings) != 1 {
		t.Fatalf("want one skill warned about, got %d: %v", len(warnings), warnings)
	}

	for _, undeclared := range []string{"docstring-in-method", "field-docstring"} {
		if !strings.Contains(warnings[0], undeclared) {
			t.Errorf("%q does not name %s, which no rule declares", warnings[0], undeclared)
		}
	}

	for _, declared := range []string{"raw-sql`", "python-bloated-docblock", "fix-at-the-source", "lists.json", "like-this"} {
		if strings.Contains(warnings[0], declared) {
			t.Errorf("%q names %s, which is not an undeclared rule", warnings[0], declared)
		}
	}
}

func TestASkillThatNamesOnlyDeclaredRulesIsNotWarnedAbout(t *testing.T) {
	const honest = "---\nname: No raw SQL\ndescription: x\n---\n\n## When it fires\n\n- **`raw-sql`** - a query at a call site.\n"

	loaded := Load(project(t, map[string]string{
		"RawSqlDetector.json":        rawSQL,
		"skills/no-raw-sql/SKILL.md": honest,
	}))

	if warnings := loaded.ClaimWarnings(); len(warnings) != 0 {
		t.Errorf("a skill naming only its own declared rule was warned about: %v", warnings)
	}
}

func TestTheSinsLineOfARenderedSkillIsReadWhereverItStands(t *testing.T) {
	const rendered = "---\nname: No raw SQL\ndescription: x\n---\n\n## Commands\n\n" +
		"- `commandments info <sin>` — what one rule flags. The sins here: `raw-sql`, `no-such-rule`.\n"

	loaded := Load(project(t, map[string]string{
		"RawSqlDetector.json":        rawSQL,
		"skills/no-raw-sql/SKILL.md": rendered,
	}))

	warnings := loaded.ClaimWarnings()
	if len(warnings) != 1 || !strings.Contains(warnings[0], "no-such-rule") {
		t.Fatalf("want the undeclared sin of the sins line named, got %v", warnings)
	}
}

func TestANameInProseIsNoClaim(t *testing.T) {
	const prose = "---\nname: No raw SQL\ndescription: x\n---\n\n# No raw SQL\n\n" +
		"Never write `no-such-rule` here, and load `commandments-php-absence` before you do.\n\n" +
		"- `another-missing-rule` - a bullet under no heading about what fires.\n"

	loaded := Load(project(t, map[string]string{
		"RawSqlDetector.json":        prose,
		"skills/no-raw-sql/SKILL.md": prose,
	}))

	if warnings := loaded.ClaimWarnings(); len(warnings) != 0 {
		t.Errorf("a name written in prose was read as a rule the skill says fires: %v", warnings)
	}
}
