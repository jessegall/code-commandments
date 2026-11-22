package rule

import (
	"encoding/json"
	"errors"
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/skill"
)

func anySkill(string) (skill.Skill, bool) {
	return nil, true
}

// written is a v2 rule over every engine whose find is the one given.
func written(find string) []byte {
	return []byte(`{"$schema":"commandments:neutral-rule/v2","engine":["php","python","csharp","typescript","vue","go"],"sin":{"name":"empty-handler","skill":"shared-exceptions"},` + find + `}`)
}

func refused(t *testing.T, text []byte, wanted ...string) {
	t.Helper()
	_, err := Parse("Guard", text, anySkill)
	if err == nil {
		t.Fatalf("the rule was read, and it should be refused")
	}
	for _, each := range wanted {
		if !strings.Contains(err.Error(), each) {
			t.Errorf("the refusal %q does not say %q", err, each)
		}
	}
}

func TestParseReadsTheSection18Rule(t *testing.T) {
	text, err := os.ReadFile("testdata/swallow-catch.json")
	if err != nil {
		t.Fatal(err)
	}
	read, err := Parse("swallow-catch", text, anySkill)
	if err != nil {
		t.Fatalf("the rule is refused: %v", err)
	}
	if read.Sin().Definition().Name != "swallow-catch" || len(read.Engines()) != 6 {
		t.Errorf("the rule reads as %q over %v", read.Sin().Definition().Name, read.Engines())
	}
}

func TestParseRefusesAnOldFormatFileNamingItsStep(t *testing.T) {
	old := []byte(`{"engine":"php","sin":{"name":"guard","skill":"shared-flow"},"find":{"select":"if","where":[{"redundantElse":true}]}}`)
	_, err := Parse("Guard", old, anySkill)
	var format *OldFormat
	if !errors.As(err, &format) {
		t.Fatalf("an old file is refused with %v", err)
	}
	format.File = ".commandments/custom/Guard.json"
	if wanted := ".commandments/custom/Guard.json:1 uses the old redundantElse step. Rewrite this rule using commandments:neutral-rule/v2: `commandments rule schema` lists the operators a step becomes."; format.Error() != wanted {
		t.Errorf("the refusal reads %q", format.Error())
	}
}

func TestAnOldScaffoldIsRefusedAtItsStepsLineWithTheStepsNewForm(t *testing.T) {
	scaffolded := []byte(`{
    "$schema": "../rule.schema.json",
    "engine": "php",
    "find": {
        "reject": [],
        "select": "call",
        "where": [
            {
                "name": "TODO-the-call-this-rule-flags"
            }
        ]
    },
    "sin": {"name": "no-raw-sql", "skill": "no-raw-sql"}
}`)
	_, err := Parse("NoRawSqlDetector", scaffolded, anySkill)
	var format *OldFormat
	if !errors.As(err, &format) {
		t.Fatalf("an old scaffold is refused with %v", err)
	}
	if wanted := `NoRawSqlDetector.json:9 uses the old name step. Rewrite this rule using commandments:neutral-rule/v2, where it reads ["=", "$<the name its select binds>.name", "TODO-the-call-this-rule-flags"].`; format.Error() != wanted {
		t.Errorf("the refusal reads %q", format.Error())
	}
}

func TestParseRefusesAnotherSchemaVersion(t *testing.T) {
	refused(t, []byte(`{"$schema":"commandments:neutral-rule/v3","engine":"php","sin":{"name":"x","skill":"y"},"find":{"select":"handler","as":"site"}}`),
		"commandments:neutral-rule/v3", "commandments:neutral-rule/v2")
}

func TestValidateRefusesARoleTheKindDoesNotDefine(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":["exists","$site.then"]}`),
		"find.where", "$site.then", "handler", `"then"`)
}

func TestValidateRefusesAnUnknownOperator(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":["ands",true,true]}`),
		"find.where", `"ands"`, `did you mean "and"`)
}

func TestValidateRefusesAWrongOperandType(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":[">",["count","$site.caughtTypes"],"three"]}`),
		"find.where", `operand 2 of ">"`, "text", "number")
}

func TestValidateRefusesAnUnboundName(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":["exists","$body"]}`),
		"find.where", "$body is not bound", "$site")
}

func TestValidateRefusesAForwardName(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","let":[["first",["count","$later"]],["later","$site.caughtTypes"]]}`),
		"find.let[0] (first)", "$later is bound further on")
}

func TestValidateRefusesAnIteratorReadOutsideItsBody(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":["and",["some","$site.caughtTypes","c",["exists","$c"]],["exists","$c"]]}`),
		"$c is not bound")
}

func TestValidateRefusesADefinitionCycle(t *testing.T) {
	refused(t, written(`"definitions":{"a":{"args":["x"],"expr":["b","$x"]},"b":{"args":["x"],"expr":["a","$x"]}},"find":{"select":"handler","as":"site","where":["a","$site"]}`),
		"definitions", "a → b → a", "cycle")
}

func TestValidateRefusesADefinitionCalledWithTheWrongCount(t *testing.T) {
	refused(t, written(`"definitions":{"empty":{"args":["x"],"expr":["=",["count","$x"],0]}},"find":{"select":"handler","as":"site","where":["empty","$site","$site"]}`),
		`"empty" takes 1 operands`)
}

func TestValidateRefusesAnUnknownKind(t *testing.T) {
	refused(t, written(`"find":{"select":"handlr","as":"site"}`), "find.select", `"handlr"`, `did you mean "handler"`)
}

func TestValidateRefusesAMisspeltKey(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","wher":true}`), `"wher"`, `did you mean "where"`)
}

func TestSchemaNamesTheV2Format(t *testing.T) {
	var schema map[string]any
	if err := json.Unmarshal(Schema(), &schema); err != nil {
		t.Fatal(err)
	}
	properties := schema["properties"].(map[string]any)
	if format := properties["$schema"].(map[string]any)["const"]; format != Format {
		t.Errorf("the schema asks for the format %v", format)
	}
	for _, key := range []string{"definitions", "find", "sin"} {
		if _, declared := properties[key]; !declared {
			t.Errorf("the schema declares no %q", key)
		}
	}
}

func TestPipelineRefusesAStageNamingTwoOperations(t *testing.T) {
	refused(t, written(`"find":{"from":"function","as":"f","pipeline":[{"where":true,"reject":false},{"emit":"$f"}]}`),
		"names one operation", "reject where")
}

func TestPipelineRefusesAGroupedNameReadBeforeItsExpand(t *testing.T) {
	refused(t, written(`"find":{"from":"function","as":"f","pipeline":[{"project":{"anchor":"$f"}},{"groupBy":["count","$f.parameters"]},{"emit":"$anchor"}]}`),
		"$anchor is held in each group's rows")
}

func TestPipelineRefusesAFindThatEmitsNothing(t *testing.T) {
	refused(t, written(`"find":{"from":"function","as":"f","pipeline":[{"where":true}]}`), "no stage emits")
}
