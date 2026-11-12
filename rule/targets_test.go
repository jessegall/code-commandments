package rule_test

import "testing"

func TestACallIsNamedByWhatItReachesNotByWhatItIsCalled(t *testing.T) {
	project := goProject(t, map[string]string{
		"go.mod": "module example.com/shop\n\ngo 1.22\n",
		"walks.go": `package shop

import (
	"io/fs"
	"os"
	"path/filepath"
)

type Tree struct{}

func (Tree) WalkDir(root string) {}

func Listed(root string) {
	filepath.WalkDir(root, func(string, fs.DirEntry, error) error { return nil })
	os.ReadDir(root)
	Tree{}.WalkDir(root)
}
`,
	})

	for check, want := range map[string]string{
		`{"targets": "path/filepath.WalkDir"}`: "[walks.go:14]",
		`{"targets": "os.ReadDir"}`:            "[walks.go:15]",
		`{"targets": "*WalkDir"}`:              "[walks.go:14 walks.go:16]",
		`{"targets": "os.*"}`:                  "[walks.go:15]",
	} {
		if found := foundAt(t, "go", `{"select": "call", "where": [`+check+`]}`, project); found != want {
			t.Errorf("%s found %s, want %s", check, found, want)
		}
	}
}
