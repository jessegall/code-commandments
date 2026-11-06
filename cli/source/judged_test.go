package source

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAnExcludedFolderARuleJudgesIsRead(t *testing.T) {
	root := treeWith(t, "src/cart.php", "tests/cart_test.php", "vendor/lib.php")

	excluded := Under(root, []string{"tests", "vendor"})

	if found := Sources(root, excluded); len(found) != 1 {
		t.Fatalf("the exclusions read %d files, want the one under src: %v", len(found), found)
	}

	judging := excluded.Judging([]string{"tests"})

	found := Sources(root, judging)
	if len(found) != 2 {
		t.Fatalf("a judged folder is read as %d files, want two: %v", len(found), found)
	}
	if judging.Covers(filepath.Join(root, "tests", "cart_test.php")) {
		t.Error("a file in the judged folder still reads as excluded")
	}
	if !judging.Covers(filepath.Join(root, "vendor", "lib.php")) {
		t.Error("the other excluded folder stopped being excluded")
	}
}

func TestJudgingNamesTheFolderHoweverItIsWritten(t *testing.T) {
	root := treeWith(t, "tests/cart_test.php")

	for name, given := range map[string][]string{
		"plain":                            {"tests"},
		"with slashes":                     {"/tests/"},
		"beside an empty":                  {"", "tests"},
		"named twice":                      {"tests", "tests"},
		"a folder with no file of its own": {"tests", "spec"},
	} {
		t.Run(name, func(t *testing.T) {
			judging := Under(root, []string{"tests"}).Judging(given)

			if judging.Covers(filepath.Join(root, "tests", "cart_test.php")) {
				t.Errorf("the folder named as %v is still excluded", given)
			}
		})
	}
}

func TestAFolderNobodyJudgesStaysExcluded(t *testing.T) {
	root := treeWith(t, "tests/cart_test.php", "spec/cart_spec.php")

	judging := Under(root, []string{"tests", "spec"}).Judging([]string{"tests"})

	if !judging.Covers(filepath.Join(root, "spec", "cart_spec.php")) {
		t.Error("judging tests opened spec as well")
	}
	if judging.IsEmpty() {
		t.Error("the exclusions read as empty, so nothing is excluded any more")
	}
}

func treeWith(t *testing.T, files ...string) string {
	t.Helper()
	root := t.TempDir()
	for _, file := range files {
		path := filepath.Join(root, file)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}
