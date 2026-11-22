package samples

import (
	"fmt"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func TestOneConstructCarriesTheSameMarkersInEveryLanguage(t *testing.T) {
	read := func(language contract.Language, path, text string) marker.Source {
		source, faults := marker.ReadSource(language, path, []byte(text))
		if len(faults) > 0 {
			t.Fatal(faults)
		}

		return source
	}
	php := read(contract.PHP, "php.php", "<?php\nwhile ($a) { // @label outer loop\n    break; // @contract link/break/destination -> outer\n}\n")
	goSame := read(contract.Go, "go.go", "package a\n\nfunc f() {\n\tfor { // @label outer loop\n\t\tbreak // @contract link/break/destination -> line(4,loop)\n\t}\n}\n")
	goOther := read(contract.Go, "go.go", "package a\n\nfunc f() {\n\tfor {\n\t\tbreak // @contract kind/break\n\t}\n}\n")
	if faults := Parity("jumps", map[contract.Language]marker.Source{contract.PHP: php, contract.Go: goSame}, nil); len(faults) > 0 {
		t.Errorf("the same markers differ: %v", faults)
	}
	var written []string
	for _, fault := range Parity("jumps", map[contract.Language]marker.Source{contract.PHP: php, contract.Go: goOther}, nil) {
		written = append(written, fmt.Sprint(fault))
	}
	want := []string{
		`jumps: go marks "kind/break", php does not`,
		`jumps: php marks "link/break/destination -> outer", go does not`,
	}
	if !reflect.DeepEqual(written, want) {
		t.Errorf("faults %q\nwant   %q", written, want)
	}
	excused := []Exclusion{{Construct: "jumps", Key: "kind/break", Language: contract.PHP, Why: "a test"}, {Construct: "jumps", Key: "link/break/destination", Language: contract.Go, Why: "a test"}}
	if faults := Parity("jumps", map[contract.Language]marker.Source{contract.PHP: php, contract.Go: goOther}, excused); len(faults) > 0 {
		t.Errorf("excluded keys still differ: %v", faults)
	}
}

func TestAnExclusionNamesARealConstructKeyAndLanguageWithAReason(t *testing.T) {
	table := "## Exclusions\n\n| Construct | Key | Language | Why |\n|---|---|---|---|\n" +
		"| jumps | kind/break | php | fine |\n" +
		"| nowhere | kind/break | php | x |\n" +
		"| jumps | kind/brake | php | x |\n" +
		"| jumps | kind/break | cobol | x |\n" +
		"| jumps | kind/break | go |  |\n"
	exclusions, faults := ExclusionsFrom("README.md", []byte(table), []string{"jumps"})
	var written []string
	for _, fault := range faults {
		written = append(written, fmt.Sprint(fault))
	}
	want := []string{
		"README.md:6: nowhere is no sample construct",
		"README.md:7: kind/brake is no contract item",
		"README.md:8: cobol is no language",
		"README.md:9: kind/break for go is excluded without a reason",
	}
	if !reflect.DeepEqual(written, want) || len(exclusions) != 1 {
		t.Errorf("faults %q\nwant   %q\nkept %d rows", written, want, len(exclusions))
	}
}
