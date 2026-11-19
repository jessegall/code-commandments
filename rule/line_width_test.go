package rule_test

import (
	"strings"
	"testing"
)

func TestARuleFlagsEachLineLongerThanItsLimit(t *testing.T) {
	source := "<?php\n\nnamespace App;\n\nfunction wide(): string\n{\n    return '" + strings.Repeat("a", 107) + "';\n}\n\n" +
		"function fits(): string\n{\n    return '" + strings.Repeat("b", 106) + "';\n}\n"
	built := phpCodebase(t, "Width.php", source)

	for query, want := range map[string]string{
		`{"select": "line", "where": [{"lineLonger": 120}]}`: "[7]",
		`{"select": "line", "where": [{"lineLonger": 119}]}`: "[7 12]",
		`{"select": "line", "where": [{"lineLonger": 121}]}`: "[]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}
