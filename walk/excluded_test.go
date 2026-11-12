package walk

import "testing"

func TestAnExclusionIsAppliedInsideTheRootItIsShiftedTo(t *testing.T) {
	given := []string{"**/dist", "**/vendor", "legacy/*", "generated", "src/viewer/tests/sample-window", "tests"}

	for _, root := range []string{
		"/project",
		"/project/.journal/worktrees/worker-1",
		"/elsewhere/worker-1",
		"/home/dist/vendor/tests/generated/project",
	} {
		excluded := Under(root, given)

		for file, covered := range map[string]bool{
			"dist/index.js":                         true,
			"src/core/web/dist/assets/index-abc.js": true,
			"src/lib/vendor/a/b/c.php":              true,
			"legacy/old.php":                        true,
			"generated/made.py":                     true,
			"generated/deep/down/made.py":           true,
			"src/viewer/tests/sample-window/a.ts":   true,
			"tests/test_order.py":                   true,
			"src/order.py":                          false,
			"src/distant/order.py":                  false,
			"src/vendored/order.py":                 false,
			"src/generated/order.py":                false,
			"src/tests/order.py":                    false,
			"src/viewer/tests/other-window/a.ts":    false,
			"legacy/deep/old.php":                   true,
		} {
			if got := excluded.Covers(root + "/" + file); got != covered {
				t.Errorf("under %s, %s is left out=%v, want %v", root, file, got, covered)
			}
		}
	}
}

func TestAnExclusionNeverReachesOutsideItsRoot(t *testing.T) {
	excluded := Under("/project/.journal/worktrees/worker-1", []string{"**/dist", "src", "*"})

	for _, outside := range []string{"/project/src/order.py", "/project/dist/index.js", "/elsewhere/order.py"} {
		if excluded.Covers(outside) {
			t.Errorf("an exclusion shifted to the worktree left out %s, which lies outside it", outside)
		}
	}
}

func TestAFolderARuleJudgesIsNotLeftOutUnderAnyRoot(t *testing.T) {
	for _, root := range []string{"/project", "/project/.journal/worktrees/worker-1"} {
		judging := Under(root, []string{"tests", "**/dist"}).Judging([]string{"tests"})

		if judging.Covers(root + "/tests/test_order.py") {
			t.Errorf("under %s, a folder a rule judges is left out", root)
		}

		if !judging.Covers(root + "/src/dist/index.js") {
			t.Errorf("under %s, judging one folder stopped the other exclusions from applying", root)
		}
	}
}
