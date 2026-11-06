package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestACodebaseNarrowedToAFileStillAnswersForTheProgram(t *testing.T) {
	codebase := sample(t)

	files := codebase.Files()
	if len(files) < 2 {
		t.Fatalf("the sample holds %d files", len(files))
	}

	whole := codebase.WhereFunction().Count()
	declarations := len(codebase.Files())

	narrowed := codebase.Reporting(files[0].Path)

	inFile := narrowed.WhereFunction().Count()
	if inFile == 0 || inFile >= whole {
		t.Errorf("narrowed to one file the query answers %d functions of %d, so it is not narrowed", inFile, whole)
	}

	for _, match := range narrowed.WhereFunction().Get() {
		if match.File() != files[0].Path {
			t.Errorf("a query narrowed to %s answered with %s", files[0].Path, match.File())
		}
	}

	if held := len(narrowed.Files()); held != declarations {
		t.Errorf("the narrowed codebase holds %d files of %d", held, declarations)
	}

	if symbol, found := declaredElsewhere(codebase, files[0].Path); found {
		if named := len(narrowed.Declarations(symbol)); named == 0 {
			t.Errorf("the narrowed codebase names no declaration of %s, which another file declares", symbol)
		}
	}

	if again := codebase.WhereFunction().Count(); again != whole {
		t.Errorf("after narrowing, the codebase answers %d functions where it answered %d", again, whole)
	}
}

func declaredElsewhere(codebase *engine.Codebase, file string) (string, bool) {
	for _, match := range codebase.WhereTypeDeclaration().Get() {
		if match.File() != file && match.Identity() != "" {
			return match.Identity(), true
		}
	}

	return "", false
}
