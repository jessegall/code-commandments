package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// TestACodebaseNarrowedToAFileStillAnswersForTheProgram holds the two halves of Reporting apart: a selector
// opens over the named file alone, and everything a check reads is still the whole program.
//
// That is what lets one edit be judged against the program. A rule's query walks every node of the codebase, so
// checking one edited file meant either reading that file as though it were the program, which is wrong for
// every rule that follows a chain of parents or a caller, or walking the whole tree to keep the findings of one
// file. Measured on a 5286-file tree: the rules that read across files cost 14.25s over the whole view and 1.75s
// asked about the edited file, on one machine at one moment.
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

	// Every file is still there to be read, which is what a check that follows a chain of parents or a caller
	// needs, and the whole-program index still names what the other files declare.
	if held := len(narrowed.Files()); held != declarations {
		t.Errorf("the narrowed codebase holds %d files of %d", held, declarations)
	}

	if symbol, found := declaredElsewhere(codebase, files[0].Path); found {
		if named := len(narrowed.Declarations(symbol)); named == 0 {
			t.Errorf("the narrowed codebase names no declaration of %s, which another file declares", symbol)
		}
	}

	// The codebase it was narrowed from is untouched.
	if again := codebase.WhereFunction().Count(); again != whole {
		t.Errorf("after narrowing, the codebase answers %d functions where it answered %d", again, whole)
	}
}

// declaredElsewhere is the symbol id of a type some file other than this one declares, which a codebase
// narrowed to this file must still name: that is what a check following a chain of parents reads.
func declaredElsewhere(codebase *engine.Codebase, file string) (string, bool) {
	for _, match := range codebase.WhereTypeDeclaration().Get() {
		if match.File() != file && match.Identity() != "" {
			return match.Identity(), true
		}
	}

	return "", false
}
