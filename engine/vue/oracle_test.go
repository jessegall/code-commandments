package vue_test

import (
	"os"
	"path/filepath"
	"reflect"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/vue"
)

const probed = `<script setup lang="ts">
const pageSizes = [50, 100, 200];
const label = computed(() => schema.value.label);
</script>
<template><div>{{ label }}</div></template>`

func TestProbeWritesANameEncodingProbeInsideScriptSetup(t *testing.T) {
	probe, ok := vue.ProbeSource(componentFrom(t, probed), []string{"pageSizes", "label"})
	if !ok {
		t.Fatal("nothing probed")
	}
	for _, want := range []string{"const __cc_pageSizes: __CcNo_pageSizes = pageSizes;", "const __cc_label: __CcNo_label = label;"} {
		if !strings.Contains(probe, want) {
			t.Errorf("probe lacks %q:\n%s", want, probe)
		}
	}
	if !strings.HasSuffix(strings.TrimSpace(probe), "</template>") {
		t.Errorf("probe does not end with the template:\n%s", probe)
	}
	if strings.Index(probe, "const __cc_pageSizes") > strings.Index(probe, "</script>") {
		t.Errorf("probe written outside the script:\n%s", probe)
	}
}

func TestProbeKeepsTheSourceAroundIt(t *testing.T) {
	probe, _ := vue.ProbeSource(componentFrom(t, probed), []string{"pageSizes"})
	for _, want := range []string{"const pageSizes = [50, 100, 200];", "<template><div>{{ label }}</div></template>"} {
		if !strings.Contains(probe, want) {
			t.Errorf("probe lost %q", want)
		}
	}
}

func TestNothingToProbeWithoutNamesOrASetupScript(t *testing.T) {
	if _, ok := vue.ProbeSource(componentFrom(t, probed), nil); ok {
		t.Error("probed no names")
	}
	plain := "<script lang=\"ts\">export default {};</script>\n<template><div /></template>"
	if _, ok := vue.ProbeSource(componentFrom(t, plain), []string{"x"}); ok {
		t.Error("probed a component without a setup script")
	}
}

func TestCheckerTypes(t *testing.T) {
	monster := "{ " + strings.Repeat("field: ComputedRef<string>; ", 20) + "}"
	cases := map[string]struct {
		output string
		want   map[string]string
	}{
		"each probe's type and local": {
			"__cc_probe_x.vue(9,7): error TS2322: Type 'number[]' is not assignable to type '__CcNo_pageSizes'.\n" +
				"__cc_probe_x.vue(11,7): error TS2322: Type 'string | null' is not assignable to type '__CcNo_label'.",
			map[string]string{"pageSizes": "number[]", "label": "string | null"},
		},
		"other diagnostics ignored": {
			"src/Real.vue(3,1): error TS2304: Cannot find name 'foo'.\n" +
				"__cc_probe_x.vue(9,7): error TS2322: Type 'MergedVariant[]' is not assignable to type '__CcNo_variants'.\n" +
				"some noise that mentions Type 'X' but nothing else",
			map[string]string{"variants": "MergedVariant[]"},
		},
		"quotes inside a type": {
			`f.vue(9,7): error TS2322: Type '"a" | "b"' is not assignable to type '__CcNo_mode'.`,
			map[string]string{"mode": `"a" | "b"`},
		},
		"a monster type dropped": {
			"f.vue(9,7): error TS2322: Type '" + monster + "' is not assignable to type '__CcNo_ops'.\n" +
				"f.vue(10,7): error TS2322: Type 'string | null' is not assignable to type '__CcNo_label'.",
			map[string]string{"label": "string | null"},
		},
		"unknown and any dropped": {
			"f.vue(9,7): error TS2322: Type 'unknown' is not assignable to type '__CcNo_a'.\n" +
				"f.vue(11,7): error TS2322: Type 'any' is not assignable to type '__CcNo_b'.",
			map[string]string{},
		},
	}
	for name, test := range cases {
		t.Run(name, func(t *testing.T) {
			if got := vue.CheckerTypes(test.output); !reflect.DeepEqual(got, test.want) {
				t.Errorf("got %v, want %v", got, test.want)
			}
		})
	}
}

// fakeTsc plays vue-tsc: in one run it finds every probe beside the components and complains about each name it types.
type fakeTsc struct {
	types     map[string]string
	binary    string
	arguments []string
	cwd       string
	runs      int
	probe     string
}

func (f *fakeTsc) Run(binary string, arguments []string, cwd string) string {
	f.binary, f.arguments, f.cwd = binary, arguments, cwd
	f.runs++
	probes, _ := filepath.Glob(cwd + "/src/__cc_probe_*.vue")
	var lines []string
	for _, probe := range probes {
		contents, _ := os.ReadFile(probe)
		f.probe = string(contents)
		for name, typed := range f.types {
			if strings.Contains(f.probe, "__CcNo_"+name) {
				lines = append(lines, filepath.Base(probe)+"(9,7): error TS2322: Type '"+typed+"' is not assignable to type '__CcNo_"+name+"'.")
			}
		}
	}

	return strings.Join(lines, "\n")
}

func oracleRoot(t *testing.T) string {
	t.Helper()
	root := t.TempDir()
	if err := os.MkdirAll(root+"/src", 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// components scans the components, each a script setup with the body, from src/ under a fresh root, and answers the
// root and each component by name.
func components(t *testing.T, bodies map[string]string) (string, map[string]vue.Component) {
	t.Helper()
	sources := map[string]string{}
	for name, body := range bodies {
		sources["src/"+name+".vue"] = "<script setup lang=\"ts\">\n" + body + "\n</script>\n<template><div /></template>\n"
	}
	scanned := map[string]vue.Component{}
	root := ""
	for _, file := range frontendtest.FromSource(t, sources).Files() {
		root = filepath.Dir(filepath.Dir(file.Path))
		scanned[strings.TrimSuffix(filepath.Base(file.Path), ".vue")] = vue.ComponentOf(file.Match(file.Root.ID))
	}

	return root, scanned
}

// componentFrom is the component of the source, scanned.
func componentFrom(t *testing.T, source string) vue.Component {
	t.Helper()
	for _, file := range frontendtest.FromSource(t, map[string]string{"Probed.vue": source}).Files() {
		return vue.ComponentOf(file.Match(file.Root.ID))
	}
	t.Fatal("nothing scanned")

	return vue.Component{}
}

func TestOracleResolvesEveryComponentInOneRun(t *testing.T) {
	root, scanned := components(t, map[string]string{"Widget": "const pageSizes = magic();", "Panel": "const label = magic();"})
	widget, panel := scanned["Widget"], scanned["Panel"]
	runner := &fakeTsc{types: map[string]string{"pageSizes": "number[]", "label": "string | null"}}
	types := vue.NewVueTscOracle(root, runner).ResolveAll([]vue.TypeQuery{{Component: widget, Names: []string{"pageSizes"}}, {Component: panel, Names: []string{"label"}}})
	if !reflect.DeepEqual(types[widget.File()], map[string]string{"pageSizes": "number[]"}) {
		t.Errorf("widget: %v", types[widget.File()])
	}
	if !reflect.DeepEqual(types[panel.File()], map[string]string{"label": "string | null"}) {
		t.Errorf("panel: %v", types[panel.File()])
	}
	if runner.runs != 1 {
		t.Errorf("the checker ran %d times", runner.runs)
	}
}

func TestOracleRunsVueTscIncrementallyWithLibCheckSkipped(t *testing.T) {
	root, scanned := components(t, map[string]string{"Widget": "const x = y();"})
	runner := &fakeTsc{}
	vue.NewVueTscOracle(root, runner).ResolveAll([]vue.TypeQuery{{Component: scanned["Widget"], Names: []string{"x"}}})
	if !strings.HasSuffix(runner.binary, "/node_modules/.bin/vue-tsc") {
		t.Errorf("ran %s", runner.binary)
	}
	for _, flag := range []string{"--skipLibCheck", "--incremental", "--noEmit"} {
		if !slices.Contains(runner.arguments, flag) {
			t.Errorf("ran without %s: %v", flag, runner.arguments)
		}
	}
	if runner.cwd != root {
		t.Errorf("ran in %s", runner.cwd)
	}
}

func TestOracleWritesProbesBesideEachComponentThenRemovesThem(t *testing.T) {
	root, scanned := components(t, map[string]string{"Widget": "const x = y();"})
	runner := &fakeTsc{}
	vue.NewVueTscOracle(root, runner).ResolveAll([]vue.TypeQuery{{Component: scanned["Widget"], Names: []string{"x"}}})
	if !strings.Contains(runner.probe, "__CcNo_x") {
		t.Errorf("the checker saw no probe: %q", runner.probe)
	}
	if left, _ := filepath.Glob(root + "/src/__cc_probe_*.vue"); len(left) > 0 {
		t.Errorf("probes left behind: %v", left)
	}
}

func TestVueTscAvailabilityFollowsItsBinary(t *testing.T) {
	root := oracleRoot(t)
	if vue.VueTscAvailable(root) {
		t.Fatal("available without a binary")
	}
	if _, ok := vue.LocateVueTsc(root+"/src", vue.ShellRunner{}); ok {
		t.Fatal("located without a binary")
	}
	if err := os.MkdirAll(root+"/node_modules/.bin", 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(root+"/node_modules/.bin/vue-tsc", nil, 0o755); err != nil {
		t.Fatal(err)
	}
	if !vue.VueTscAvailable(root) {
		t.Error("unavailable beside its binary")
	}
	if _, ok := vue.LocateVueTsc(root+"/src", vue.ShellRunner{}); !ok {
		t.Error("not located from a folder below the project")
	}
}
