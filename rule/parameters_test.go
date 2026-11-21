package rule

import (
	"strings"
	"testing"
)

const layeredRule = `"parameters":{"minClasses":{"type":"number","default":2,"description":"How many owners share the group."},"layer":{"type":"list","default":[],"description":"The layers, each with the layers it may use."}},` +
	`"find":{"select":"handler","as":"site","where":[">=",["count","$site.caughtTypes"],"$param.minClasses"]}`

func TestParameterReadsItsDefaultAndTheValueSet(t *testing.T) {
	read, err := Parse("probe", written(layeredRule), anySkill)
	if err != nil {
		t.Fatal(err)
	}
	if found := read.Find(expected(t, "bare-except-pass", "python")); len(found) != 0 {
		t.Errorf("by default a bare except is under the threshold: %d found", len(found))
	}
	set, err := read.WithParameter("minClasses", 0.0)
	if err != nil {
		t.Fatal(err)
	}
	if found := set.Find(expected(t, "bare-except-pass", "python")); len(found) != 1 {
		t.Errorf("with minClasses 0 the bare except is found %d times", len(found))
	}
	if set.CacheKey() == "" || read.parameters().String() == set.parameters().String() {
		t.Errorf("setting a parameter leaves its value as it was")
	}
	if _, err := read.WithParameter("layer", []any{[]any{"App\\Ui", []any{"App\\Domain"}}}); err != nil {
		t.Errorf("a layer list is refused: %v", err)
	}
}

func TestParameterRefusals(t *testing.T) {
	refused(t, written(`"find":{"select":"handler","as":"site","where":[">",["count","$site.caughtTypes"],"$param.minClasses"]}`),
		"$param.minClasses reads a parameter the rule does not declare")
	refused(t, written(`"parameters":{"minClasses":{"type":"number","default":"two","description":"x"}},"find":{"select":"handler","as":"site"}`),
		"parameters.minClasses", "no number")
	refused(t, written(`"parameters":{"minClasses":{"type":"integer","default":2,"description":"x"}},"find":{"select":"handler","as":"site"}`),
		"parameters.minClasses", `"integer"`)

	read, err := Parse("probe", written(layeredRule), anySkill)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := read.WithParameter("minClass", 3.0); err == nil || !strings.Contains(err.Error(), `no parameter "minClass"`) || !strings.Contains(err.Error(), "probe") {
		t.Errorf("an unknown parameter is refused with %v", err)
	}
	if _, err := read.WithParameter("minClasses", "three"); err == nil || !strings.Contains(err.Error(), "is a number") {
		t.Errorf("a wrong type is refused with %v", err)
	}
}

func TestRequiresGatesARuleOnItsPackage(t *testing.T) {
	read, err := Parse("probe", []byte(`{"$schema":"commandments:neutral-rule/v2","engine":"php","sin":{"name":"probe","skill":"x","requires":{"name":"laravel/framework","ecosystem":"composer"}},"find":{"select":"call","as":"c"}}`), anySkill)
	if err != nil {
		t.Fatal(err)
	}
	if required := read.Sin().Definition().Requires; required.Name != "laravel/framework" || string(required.Ecosystem) != "composer" {
		t.Errorf("the rule requires %v", required)
	}
}
