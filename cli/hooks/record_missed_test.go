package hooks

import (
	"fmt"
	"strings"
	"testing"
)

// A judging's line names the files the cache missed, relative to the checkout and at most a dozen, so a cache that keeps
// missing the same files can be traced from the log.
func TestAJudgingsLineNamesTheFilesTheCacheMissed(t *testing.T) {
	var missed []string
	for number := range 14 {
		missed = append(missed, fmt.Sprintf("/work/worker-1/src/shop/file%d.py", number))
	}
	line := Judged{Checkout: "/work/worker-1", Kept: 3, Missed: missed}.Line()

	if !strings.Contains(line, "missed src/shop/file0.py, src/shop/file1.py") || !strings.Contains(line, "and 2 more") {
		t.Errorf("the line does not name the files the cache missed: %s", line)
	}
	if strings.Contains(line, "file12.py") {
		t.Errorf("the line names more than a dozen missed files: %s", line)
	}
}
