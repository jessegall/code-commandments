package marker

import (
	"fmt"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestEachMarkerSpeaksAboutItsOwnLineOrTheCodeBelowItsStack(t *testing.T) {
	for _, each := range []struct {
		language contract.Language
		source   string
		lines    map[string]int
		faults   []string
	}{
		{contract.PHP, "<?php\nwhile ($ready) { // @label outer loop\n    // @contract kind/break\n    // @contract link/break/destination -> outer\n    break;\n}\n$a = '// @contract kind/loop';\n// @contract kind/lop\n$b = 1;\n// @contract kind/if\n", map[string]int{
			"outer": 2, "kind/break": 5, "link/break/destination": 5,
		}, []string{
			"sample.php:8: kind/lop is no contract item",
			"sample.php:10: the marker stands before no code: a blank line or the end of the file follows it",
		}},
		{contract.Python, "LIMIT = 3\nreturn LIMIT  # @contract fact/*/constantValue on reference = 3\n# @contract kind/if\n\nif LIMIT:\n    pass\nprint(\"# @contract kind/loop\")\n", map[string]int{
			"fact/*/constantValue": 2,
		}, []string{
			"sample.py:3: the marker stands before no code: a blank line or the end of the file follows it",
		}},
		{contract.Vue, "<script setup lang=\"ts\">\n// @contract kind/variable\nconst a = 1\n</script>\n\n<template>\n    <!-- @contract kind/element -->\n    <p>{{ a }}</p>\n</template>\n", map[string]int{
			"kind/variable": 3, "kind/element": 8,
		}, nil},
	} {
		path := "sample." + contract.ExtensionsOf(each.language)[0]
		markers, faults := Read(each.language, path, []byte(each.source))
		lines := map[string]int{}
		for _, marker := range markers {
			lines[marker.Key+marker.Label] = marker.Line
		}
		if !reflect.DeepEqual(lines, each.lines) {
			t.Errorf("%s: markers on lines %v, want %v", each.language, lines, each.lines)
		}
		var written []string
		for _, fault := range faults {
			written = append(written, fmt.Sprint(fault))
		}
		if !reflect.DeepEqual(written, each.faults) {
			t.Errorf("%s: faults %q, want %q", each.language, written, each.faults)
		}
	}
}
