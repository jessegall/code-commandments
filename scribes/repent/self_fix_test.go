package repent_test

import (
	"github.com/jessegall/code-commandments/scribes"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/scribes/repent"
	"github.com/jessegall/code-commandments/skill"
)

// ownRule is a project's TypeScript rule, with the fix given or none.
func ownRule(t *testing.T, name, find, fix string) rule.Rule {
	t.Helper()

	written := `{"engine": "typescript", "sin": {"name": "` + name + `", "skill": "typescript/flow"}, "find": ` + find
	if fix != "" {
		written += `, "fix": ` + fix
	}

	read, err := rule.Parse(name, []byte(written+`}`), skill.Slugged)
	if err != nil {
		t.Fatalf("%s: %v", name, err)
	}

	return read
}

func TestRepentAppliesTheFixAProjectsTypeScriptRuleDeclares(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	path := filepath.Join(root, "Basket.tsx")
	source := `import { useMemo } from 'react';

export function Basket({ items, price, rate }: { items: string[]; price: number; rate: number }) {
  const total = useMemo(() => price * rate, [price, rate]);

  return <div className="ml-4 text-left">{items.length && <span>{total}</span>}</div>;
}
`
	if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}

	given := []detectors.Detector{
		ownRule(t, "numeric-and-render", `{"select": "kind:BinaryExpression", "where": [{"of": "child:left", "is": "member-access"}, {"of": "child:right", "kind": "JsxElement"}]}`,
			`{"replace": "{child:left} > 0 && {child:right}"}`),
		ownRule(t, "manual-memo", `{"select": "call", "where": [{"name": "useMemo"}], "reject": [{"of": "child:arguments", "descendant": {"kind": "Block"}}]}`,
			`{"replace": "{child:arguments/child:body}"}`),
		ownRule(t, "physical-direction", `{"select": "kind:StringLiteral", "where": [{"textMatches": "(^|\\s)ml-|text-left"}]}`,
			`{"substitute": [{"matches": "(^|[\\s\"'])ml-", "with": "${1}ms-"}, {"matches": "\\btext-left\\b", "with": "text-start"}]}`),
		ownRule(t, "reported-only", `{"select": "call", "where": [{"name": "String"}]}`, ""),
	}

	converged, err := repent.Run([]string{root}, everywhere{}, scribes.NothingFiled{}, given, "")
	if err != nil {
		t.Fatal(err)
	}

	want := `import { useMemo } from 'react';

export function Basket({ items, price, rate }: { items: string[]; price: number; rate: number }) {
  const total = price * rate;

  return <div className="ms-4 text-start">{items.length > 0 && <span>{total}</span>}</div>;
}
`
	if got := converged.Files.Content(path); got != want {
		t.Errorf("repent wrote\n%s\nwant\n%s", got, want)
	}

	if !converged.Settled || len(converged.Skipped) != 0 {
		t.Errorf("the run settles with no step skipped: settled %v, skipped %v", converged.Settled, converged.Skipped)
	}

	var steps []string
	for _, step := range repent.Chain(nil, nil, given).Steps() {
		steps = append(steps, step.Name())
	}

	for _, name := range []string{"numeric-and-render", "manual-memo", "physical-direction"} {
		if !slices.Contains(steps, name) {
			t.Errorf("a rule that declares a fix gets a step: %s is not among %v", name, steps)
		}
	}

	if slices.Contains(steps, "reported-only") {
		t.Error("a rule that declares no fix gets no step")
	}
}
