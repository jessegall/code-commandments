package render_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skill/render"
)

// golden is the worked examples the PHP fixtures carve, written by testdata/examples.php.
func golden(t *testing.T) render.Examples {
	t.Helper()
	raw, err := os.ReadFile(filepath.Join("testdata", "examples.json"))
	if err != nil {
		t.Fatal(err)
	}
	var examples render.Examples
	if err := json.Unmarshal(raw, &examples); err != nil {
		t.Fatal(err)
	}

	return examples
}

// TestEverySkillRendersAsItIsPublished holds every skill's documents, rendered from the examples the PHP fixtures
// carve, to the files the PHP generator wrote under skills/commandments, byte for byte.
func TestEverySkillRendersAsItIsPublished(t *testing.T) {
	examples := golden(t)
	for _, teaching := range skill.All() {
		slug := teaching.Definition().Slug
		folder := filepath.Join("..", "..", "skills", "commandments", slug)
		for name, rendered := range render.Documents(teaching, examples) {
			published, err := os.ReadFile(filepath.Join(folder, name))
			if err != nil {
				t.Errorf("%s/%s is not published: %v", slug, name, err)
				continue
			}
			if string(published) != rendered {
				t.Errorf("%s/%s renders otherwise:\n%s", slug, name, firstDifference(string(published), rendered))
			}
		}
	}
}

// firstDifference is the line where two texts part, as each has it.
func firstDifference(want, got string) string {
	line := 1
	for i := 0; i < len(want) && i < len(got); i++ {
		if want[i] != got[i] {
			start := i
			for start > 0 && want[start-1] != '\n' {
				start--
			}

			return "line " + strconv.Itoa(line) + "\n  published: " + upToLine(want[start:]) + "\n  rendered:  " + upToLine(got[start:])
		}
		if want[i] == '\n' {
			line++
		}
	}

	return "one ends where the other goes on (" + strconv.Itoa(len(want)) + " against " + strconv.Itoa(len(got)) + " bytes)"
}

func upToLine(text string) string {
	for i, character := range text {
		if character == '\n' {
			return text[:i]
		}
	}

	return text
}
