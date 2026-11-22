package rule

import (
	"errors"
	"strings"
	"testing"
)

func named(origin, path, sin, find string) Source {
	text := strings.Replace(string(written(find)), `"name":"empty-handler"`, `"name":"`+sin+`"`, 1)

	return Source{Origin: origin, Path: path, Text: []byte(text)}
}

func TestLoaderReadsInstalledAndProjectRulesAlike(t *testing.T) {
	library := Load([]Source{
		named(Installed, "rules/shared-exceptions/empty.json", "empty", `"find":{"select":"handler","as":"site"}`),
		named(Project, ".commandments/custom/Own.json", "own", `"find":{"select":"handler","as":"site"}`),
	}, anySkill)
	if len(library.Problems) != 0 || len(library.Rules) != 2 || library.Rules[0].Origin() != Installed || library.Rules[1].Origin() != Project {
		t.Errorf("the loader read %d rules with problems %v", len(library.Rules), library.Problems)
	}
}

func TestLoaderReportsAnInvalidFileAndKeepsTheOthers(t *testing.T) {
	library := Load([]Source{
		named(Project, ".commandments/custom/Broken.json", "broken", `"find":{"select":"handlr","as":"site"}`),
		named(Project, ".commandments/custom/Fine.json", "fine", `"find":{"select":"handler","as":"site"}`),
	}, anySkill)
	if len(library.Rules) != 1 || len(library.Problems) != 1 || !strings.HasPrefix(library.Problems[0].Error(), ".commandments/custom/Broken.json: ") {
		t.Errorf("the loader kept %d rules and said %v", len(library.Rules), library.Problems)
	}
}

func TestLoaderNamesAnOldFormatFileByItsPath(t *testing.T) {
	old := Source{Origin: Project, Path: ".commandments/custom/Guard.json", Text: []byte(`{"engine":"php","sin":{"name":"guard","skill":"x"},"find":{"select":"if","where":[{"redundantElse":true}]}}`)}
	library := Load([]Source{old}, anySkill)
	var format *OldFormat
	if len(library.Problems) != 1 || !errors.As(library.Problems[0], &format) ||
		library.Problems[0].Error() != ".commandments/custom/Guard.json:1 uses the old redundantElse step. Rewrite this rule using commandments:neutral-rule/v2: `commandments rule schema` lists the operators a step becomes." {
		t.Errorf("the loader said %v", library.Problems)
	}
}

func TestAliasClaimedTwiceIsADiagnosticNamingBothFiles(t *testing.T) {
	first := Source{Origin: Installed, Path: "rules/shared-dependencies/namespace-cycle.json", Text: []byte(`{"$schema":"commandments:neutral-rule/v2","engine":"php","sin":{"name":"namespace-cycle","aliases":["python-namespace-cycle"],"skill":"x"},"find":{"select":"import","as":"i"}}`)}
	second := named(Project, ".commandments/custom/Cycle.json", "python-namespace-cycle", `"find":{"select":"import","as":"i"}`)
	library := Load([]Source{first, second}, anySkill)
	if len(library.Rules) != 1 || len(library.Problems) != 1 ||
		!strings.Contains(library.Problems[0].Error(), ".commandments/custom/Cycle.json") || !strings.Contains(library.Problems[0].Error(), "rules/shared-dependencies/namespace-cycle.json") {
		t.Errorf("the loader kept %d rules and said %v", len(library.Rules), library.Problems)
	}
}

func TestLoaderBringsInAnotherFilesDefinition(t *testing.T) {
	library := Load([]Source{
		named(Installed, "rules/shared-exceptions/shapes.json", "shapes", `"definitions":{"bare":{"args":["h"],"expr":["not",["exists","$h.binding"]]},"silent":{"args":["h"],"expr":["and",["bare","$h"],["=",["count","$h.body.statements"],0]]}},"find":{"select":"handler","as":"site","where":["shapes.silent","$site"]}`),
		named(Project, ".commandments/custom/Own.json", "own", `"find":{"select":"handler","as":"site","where":["shapes.silent","$site"]}`),
	}, anySkill)
	if len(library.Problems) != 0 || len(library.Rules) != 2 {
		t.Fatalf("the loader said %v", library.Problems)
	}
	brought := library.Rules[1].read.Definitions
	if _, calls := brought["shapes.silent"]; !calls {
		t.Errorf("the project rule holds %v", brought)
	}
	if _, inTurn := brought["shapes.bare"]; !inTurn {
		t.Errorf("the definition the brought one calls is not brought: %v", brought)
	}
}

func TestAmbiguousReferenceNamesBothFiles(t *testing.T) {
	library := Load([]Source{
		named(Installed, "rules/shared-exceptions/shapes.json", "shapes", `"definitions":{"bare":{"args":["h"],"expr":["exists","$h.binding"]}},"find":{"select":"handler","as":"site"}`),
		named(Project, ".commandments/custom/shapes.json", "own-shapes", `"definitions":{"bare":{"args":["h"],"expr":["exists","$h.binding"]}},"find":{"select":"handler","as":"site"}`),
		named(Project, ".commandments/custom/Own.json", "own", `"find":{"select":"handler","as":"site","where":["shapes.bare","$site"]}`),
	}, anySkill)
	if len(library.Problems) != 1 || !strings.Contains(library.Problems[0].Error(), "rules/shared-exceptions/shapes.json and .commandments/custom/shapes.json") {
		t.Errorf("the loader said %v", library.Problems)
	}
	qualified := Load([]Source{
		named(Installed, "rules/shared-exceptions/shapes.json", "shapes", `"definitions":{"bare":{"args":["h"],"expr":["exists","$h.binding"]}},"find":{"select":"handler","as":"site"}`),
		named(Project, ".commandments/custom/shapes.json", "own-shapes", `"definitions":{"bare":{"args":["h"],"expr":["exists","$h.binding"]}},"find":{"select":"handler","as":"site"}`),
		named(Project, ".commandments/custom/Own.json", "own", `"find":{"select":"handler","as":"site","where":["installed/shapes.bare","$site"]}`),
	}, anySkill)
	if len(qualified.Problems) != 0 {
		t.Errorf("a qualified reference is refused: %v", qualified.Problems)
	}
}
