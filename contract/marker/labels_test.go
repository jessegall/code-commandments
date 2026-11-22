package marker

import (
	"fmt"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestATargetNamesALabelOfItsFileOrALineThatIsThere(t *testing.T) {
	source := "<?php\n" +
		"while ($a) { // @label outer loop\n" +
		"    break; // @contract link/break/destination -> inner\n" +
		"    while ($b) { // @label inner loop\n" +
		"        break; // @contract link/break/destination -> missing\n" +
		"        continue; // @contract link/continue/destination -> line(4,loop)\n" +
		"        continue; // @contract link/continue/destination -> line(40,loop)\n" +
		"    } // @label outer block\n" +
		"}\n"
	read, faults := ReadSource(contract.PHP, "sample.php", []byte(source))
	if len(faults) > 0 {
		t.Fatal(faults)
	}
	var written []string
	for _, fault := range CheckTargets([]Source{read}) {
		written = append(written, fmt.Sprint(fault))
	}
	want := []string{
		"sample.php:8: the label outer is defined twice, first on line 2",
		"sample.php:5: no label missing in this file",
		"sample.php:7: line(40,loop) is past the file's last line 10",
	}
	if !reflect.DeepEqual(written, want) {
		t.Errorf("faults %q\nwant   %q", written, want)
	}
}
