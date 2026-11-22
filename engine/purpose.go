package engine

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Purpose is what a file is for: ordinary code, a test, or a fixture a test reads (contract/CONTRACT.md, file.purpose).
type Purpose string

const (
	Ordinary    Purpose = "ordinary"
	Testing     Purpose = "test"
	TestFixture Purpose = "test-fixture"
)

var testFolders = []string{"tests", "test", "__tests__"}

// Purpose is derived once per file from its evidence: a fixture folder under a test folder or a testdata folder makes a
// fixture; the bridge's test fact, a name the language's test tools run, or a test folder makes a test.
func (f *File) Purpose() Purpose {
	f.purposing.Do(func() {
		f.purpose = purposeOf(f.Language(), f.Path, f.FromProject(), f.Test)
	})

	return f.purpose
}

func purposeOf(language contract.Language, path, fromProject string, test bool) Purpose {
	switch {
	case InFolderNamed(fromProject, "testdata") || inFixtureFolder(fromProject):
		return TestFixture
	case test || contract.IsTestName(language, path) || inTestFolder(fromProject):
		return Testing
	}

	return Ordinary
}

func inTestFolder(path string) bool {
	return InFolderNamed(strings.ToLower(path), testFolders...)
}

func inFixtureFolder(path string) bool {
	folder := "/" + strings.ToLower(strings.ReplaceAll(path, `\`, "/"))
	for _, test := range testFolders {
		if strings.Contains(folder, "/"+test+"/fixtures/") {
			return true
		}
	}

	return false
}
