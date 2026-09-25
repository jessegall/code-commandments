package python_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

func TestTheCallIndexAnswersAsThePHPOneDoes(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]struct {
		Callers        []string `json:"callers"`
		Override       bool     `json:"override"`
		Overridden     bool     `json:"overridden"`
		ExtendsOutside bool     `json:"extendsOutside"`
	}
	golden(t, "calls", &want)
	found := defs(codebase)
	for symbol, answer := range want {
		var callers []string
		override, overridden, outside := false, false, false
		for _, def := range found[symbol] {
			for _, call := range codebase.Program.CallersOf(def) {
				if at := place(call); !slices.Contains(callers, at) {
					callers = append(callers, at)
				}
			}
			override = codebase.Program.IsOverride(def)
			overridden = codebase.Program.IsOverridden(def)
			outside = codebase.Program.ExtendsOutside(def)
		}
		slices.Sort(callers)
		if len(found[symbol]) == 0 {
			t.Errorf("%s: no def", symbol)
			continue
		}
		if strings.Join(callers, ",") != strings.Join(answer.Callers, ",") {
			t.Errorf("%s: callers %v, not %v", symbol, callers, answer.Callers)
		}
		if override != answer.Override || overridden != answer.Overridden || outside != answer.ExtendsOutside {
			t.Errorf("%s: override %v overridden %v outside %v, not %+v", symbol, override, overridden, outside, answer)
		}
	}
}

func TestEveryModuleReachesTheModulesThePHPIndexSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string][]string
	golden(t, "imports", &want)
	for _, module := range codebase.Program.Modules() {
		path := strings.TrimPrefix(module.File.Path, fixture.root+"/")
		var reached []string
		for _, imported := range module.Imports() {
			if at := strings.TrimPrefix(imported.Module.File.Path, fixture.root+"/"); !slices.Contains(reached, at) {
				reached = append(reached, at)
			}
		}
		slices.Sort(reached)
		if strings.Join(reached, ",") != strings.Join(want[path], ",") {
			t.Errorf("%s reaches %v, not %v", path, reached, want[path])
		}
	}
}

func TestEachCallSiteBindsAsThePHPIndexSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]struct {
		Target     string             `json:"target"`
		Arguments  *map[string]string `json:"arguments"`
		LiteralKey bool               `json:"literalKey"`
	}
	golden(t, "call-sites", &want)
	seen := 0
	for _, match := range codebase.WhereCall().Get() {
		call := python.Node{Match: match}
		source, _ := match.Source().Source()
		key := strings.TrimPrefix(match.File(), fixture.root+"/") + "@" + itoa(match.Node().Span.Start) + "-" + itoa(match.Node().Span.End)
		target, ok := codebase.Program.TargetOf(call)
		answer, expected := want[key]
		if ok != expected {
			t.Errorf("%s: resolved %v, PHP %v", key, ok, expected)
			continue
		}
		if !ok {
			continue
		}
		seen++
		if target.Node().Symbol != answer.Target {
			t.Errorf("%s: targets %s, not %s", key, target.Node().Symbol, answer.Target)
		}
		bound, ok := codebase.Program.ArgumentsAt(call)
		if ok != (answer.Arguments != nil) {
			t.Errorf("%s: binds %v, PHP %v", key, ok, answer.Arguments != nil)
		}
		for parameter, argument := range bound {
			span := argument.Node().Span
			if written := string(source[span.Start:span.End]); answer.Arguments != nil && (*answer.Arguments)[parameter] != written {
				t.Errorf("%s: %s receives %q, not %q", key, parameter, written, (*answer.Arguments)[parameter])
			}
		}
		if answer.Arguments != nil && len(bound) != len(*answer.Arguments) {
			t.Errorf("%s: binds %d parameters, not %d", key, len(bound), len(*answer.Arguments))
		}
		if codebase.Program.PassesLiteralKey(call) != answer.LiteralKey {
			t.Errorf("%s: passes a literal key %v", key, !answer.LiteralKey)
		}
	}
	if seen != len(want) {
		t.Errorf("%d resolved calls, not %d", seen, len(want))
	}
}
