package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/rule"
)

// component holds the three mechanical sins a project's TypeScript rules fix: a count guarding JSX, a manual memo,
// and physical-direction utilities.
const component = `import { useMemo } from 'react';

export function Basket({ items, price, rate }: { items: string[]; price: number; rate: number }) {
  const total = useMemo(() => price * rate, [price, rate]);
  const label = useMemo(() => { return String(total); }, [total]);

  return <div className="ml-4 text-left hover:pl-2">{items.length && <span>{label}</span>}</div>;
}
`

// fixing is the TypeScript rule the query and fix make, failing the test when it cannot be read.
func fixing(t *testing.T, query, fix string) rule.Rule {
	t.Helper()

	written := `{"engine": "typescript", "sin": {"name": "x", "description": "x", "skill": "typescript/flow"}, "find": ` + query + `, "fix": ` + fix + `}`
	read, err := rule.Parse("XDetector", []byte(written), shipped)
	if err != nil {
		t.Fatalf("%s: %v", fix, err)
	}

	return read
}

// fixes are the text the rule's fix writes for each node it finds, a node it writes nothing for left out.
func fixes(read rule.Rule, codebase *engine.Codebase) []string {
	var written []string
	for _, found := range read.Find(codebase) {
		if text, fixed := read.FixFor(found); fixed {
			written = append(written, text)
		}
	}

	return written
}

func TestARuleWritesTheFixItDeclaresForEachNodeItFinds(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/Basket.tsx": component})

	for _, each := range []struct {
		name, query, fix, want string
	}{
		{
			"numeric-and-render",
			`{"select": "kind:BinaryExpression", "where": [{"of": "child:left", "is": "member-access"}, {"of": "child:right", "kind": "JsxElement"}]}`,
			`{"replace": "{child:left} > 0 && {child:right}"}`,
			"items.length > 0 && <span>{label}</span>",
		},
		{
			"manual-memo, the rule narrowed to an expression body so a block body is left for a human",
			`{"select": "call", "where": [{"name": "useMemo"}], "reject": [{"of": "child:arguments", "descendant": {"kind": "Block"}}]}`,
			`{"replace": "{child:arguments/child:body}"}`,
			"price * rate",
		},
		{
			"physical-direction",
			`{"select": "kind:StringLiteral", "where": [{"textMatches": "(^|\\s|:)(ml|pl)-|text-left"}]}`,
			`{"substitute": [{"matches": "(^|[\\s\"':])ml-", "with": "${1}ms-"}, {"matches": "(^|[\\s\"':])pl-", "with": "${1}ps-"}, {"matches": "\\btext-left\\b", "with": "text-start"}]}`,
			`"ms-4 text-start hover:ps-2"`,
		},
	} {
		if got := strings.Join(fixes(fixing(t, each.query, each.fix), codebase), "|"); got != each.want {
			t.Errorf("%s: wrote %q, want %q", each.name, got, each.want)
		}
	}
}

func TestAFixWritesNothingWhereItCannotReachOrChangesNothing(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/Basket.tsx": component})

	unreached := fixing(t, `{"select": "call", "where": [{"name": "useMemo"}]}`, `{"replace": "{child:typeArguments}"}`)
	if got := fixes(unreached, codebase); len(got) != 0 {
		t.Errorf("a placeholder that reaches no node writes nothing, wrote %q", got)
	}

	unchanged := fixing(t, `{"select": "kind:StringLiteral"}`, `{"substitute": [{"matches": "margin-left", "with": "margin-inline-start"}]}`)
	if got := fixes(unchanged, codebase); len(got) != 0 {
		t.Errorf("a substitution that matches nothing writes nothing, wrote %q", got)
	}

	if declared := fixing(t, `{"select": "call"}`, `{"replace": "x"}`); !declared.Fixes() {
		t.Error("a rule with a fix says it fixes")
	}

	plain, err := rule.Parse("XDetector", []byte(`{"engine": "typescript", "sin": {"name": "x", "skill": "typescript/flow"}, "find": {"select": "call"}}`), shipped)
	if err != nil {
		t.Fatal(err)
	}

	if _, fixed := plain.FixFor(plain.Find(codebase)[0]); plain.Fixes() || fixed {
		t.Error("a rule without a fix fixes nothing")
	}
}

func TestAFixTheToolCannotRunSaysWhy(t *testing.T) {
	for fix, reason := range map[string]string{
		`{}`: "one of replace and substitute",
		`{"replace": "x", "substitute": [{"matches": "a", "with": "b"}]}`: "one of replace and substitute",
		`{"replace": "{child:left}/{sibling}"}`:                           `placeholder "sibling"`,
		`{"replace": "{child:left/grandparent}"}`:                         `placeholder "grandparent"`,
		`{"substitute": [{"matches": "(", "with": "b"}]}`:                 "no regular expression",
		`{"replace": "x", "extra": 1}`:                                    `no check or key is called "extra"`,
	} {
		written := `{"engine": "typescript", "sin": {"name": "x", "skill": "typescript/flow"}, "find": {"select": "call"}, "fix": ` + fix + `}`
		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want it to say %q", fix, err, reason)
		}
	}
}
