package packages_test

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestTheShippedPackagesExcuseWhatPhpsExcuse(t *testing.T) {
	codebase := shop.Codebase(t)
	shop.Parity(t, "exemptions", func(answer shop.Answer, node engine.Match) any {
		excused := map[string]bool{}
		for _, tag := range packages.Tags {
			excused[tag.Slug] = excuses(t, codebase, tag, answer)
		}

		return excused
	})
}

func excuses(t *testing.T, codebase *engine.Codebase, tag packages.Tag, answer shop.Answer) bool {
	var method [2]string
	if answer.Kind == "Stmt_ClassMethod" {
		if err := json.Unmarshal(answer.Ask, &method); err != nil {
			t.Fatal(err)
		}

		return packages.Excuses(codebase, tag, method[0], method[1])
	}
	var name string
	if err := json.Unmarshal(answer.Ask, &name); err != nil {
		t.Fatal(err)
	}
	if answer.Kind == "Attribute" {
		return packages.ExcusesAttribute(codebase, tag, name)
	}

	return packages.Excuses(codebase, tag, name, "")
}

func TestAProjectsOwnPackageExcusesBesideTheShippedOnes(t *testing.T) {
	codebase := shop.Codebase(t)
	packages.Use(codebase, packages.For(controlSignals{}))
	defer packages.Use(codebase, packages.For())
	if !packages.Excuses(codebase, packages.ControlSignal, `Shop\Stop`, "") {
		t.Error("the project's own package excuses nothing")
	}
	if !packages.Excuses(codebase, packages.Boundary, `Illuminate\Http\Request`, "") {
		t.Error("the shipped packages stop excusing once the project names its own")
	}
}

type controlSignals struct{}

func (controlSignals) Register(exemptions *packages.Exemptions) {
	exemptions.Exempt(packages.ControlSignal).Classes(`Shop\Stop`)
}
