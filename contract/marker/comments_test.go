package marker

import (
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestEveryLanguageFindsItsRealCommentsAndNoneInsideItsStrings(t *testing.T) {
	for _, each := range []struct {
		language contract.Language
		source   string
		want     []Comment
	}{
		{contract.PHP, "<?php\n// alone\n$a = '// not'; # after\n#[Attr]\n$b = <<<EOT\n// quoted\nEOT;\n/* block */\n", []Comment{
			{Start: 6, End: 14, Line: 2, Column: 1, Text: "alone"},
			{Start: 30, End: 37, Line: 3, Column: 16, Text: "after", Trailing: true},
			{Start: 73, End: 84, Line: 8, Column: 1, Text: "block"}}},
		{contract.TypeScript, "const a = `x ${'// no'} // no`\n// yes\nconst b = 1 // after\n", []Comment{
			{Start: 31, End: 37, Line: 2, Column: 1, Text: "yes"},
			{Start: 50, End: 58, Line: 3, Column: 13, Text: "after", Trailing: true}}},
		{contract.Python, "a = \"# no\"\nb = '''\n# no\n'''\n# yes\nc = 1  # after\n", []Comment{
			{Start: 28, End: 33, Line: 5, Column: 1, Text: "yes"},
			{Start: 41, End: 48, Line: 6, Column: 8, Text: "after", Trailing: true}}},
		{contract.CSharp, "var a = @\"C:\\\"; // after\nvar b = \"// no\";\n/* yes */\n", []Comment{
			{Start: 16, End: 24, Line: 1, Column: 17, Text: "after", Trailing: true},
			{Start: 42, End: 51, Line: 3, Column: 1, Text: "yes"}}},
		{contract.Go, "package a\n\nvar b = `// no`\nvar c = '/' // after\n// yes\n", []Comment{
			{Start: 39, End: 47, Line: 4, Column: 13, Text: "after", Trailing: true},
			{Start: 48, End: 54, Line: 5, Column: 1, Text: "yes"}}},
		{contract.Vue, "<script setup lang=\"ts\">\nconst a = '<!-- no -->' // script\n</script>\n\n<template>\n    <!-- template -->\n    <p>// no</p>\n</template>\n", []Comment{
			{Start: 49, End: 58, Line: 2, Column: 25, Text: "script", Trailing: true},
			{Start: 85, End: 102, Line: 6, Column: 5, Text: "template"}}},
	} {
		if got := Comments(each.language, []byte(each.source)); !reflect.DeepEqual(got, each.want) {
			t.Errorf("%s:\nfound %+v\nwant  %+v", each.language, got, each.want)
		}
	}
}
