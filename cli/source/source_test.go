package source

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func TestAGlobMatchesAsPhpFnmatchDoes(t *testing.T) {
	raw, err := os.ReadFile("testdata/fnmatch.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Pattern  string `json:"pattern"`
		Name     string `json:"name"`
		Pathname bool   `json:"pathname"`
		Want     bool   `json:"want"`
	}
	json.Unmarshal(raw, &cases)

	for _, c := range cases {
		if got := fnmatch(c.Pattern, c.Name, c.Pathname); got != c.Want {
			t.Errorf("fnmatch(%q, %q, %v) = %v", c.Pattern, c.Name, c.Pathname, got)
		}
	}
}

func TestTheWalkSkipsWhatIsNeverSourceAndWhatTheProjectExcluded(t *testing.T) {
	root := t.TempDir()

	for _, file := range []string{
		"src/A.php", "src/b.vue", "src/c.ts", "src/d.py", "src/E.cs", "src/notes.md",
		"vendor/x.php", "node_modules/y.ts", ".hidden/z.php", "pkg.egg-info/p.py",
		"venv/pyvenv.cfg", "venv/lib/q.py", "app/app.csproj", "app/bin/Out.cs", "app/Keep.cs",
		"generated/G.php", "legacy/old/L.php",
	} {
		os.MkdirAll(filepath.Join(root, filepath.Dir(file)), 0o755)
		os.WriteFile(filepath.Join(root, file), nil, 0o644)
	}

	var got []string

	for _, file := range Sources(root, Under(root, []string{"generated", "legacy/*"})) {
		relative, _ := filepath.Rel(root, file)
		got = append(got, relative)
	}

	slices.Sort(got)
	want := []string{"app/Keep.cs", "src/A.php", "src/E.cs", "src/b.vue", "src/c.ts", "src/d.py"}

	if !slices.Equal(got, want) {
		t.Errorf("sources %v", got)
	}
}

func TestAFileIsReadByItsExtension(t *testing.T) {
	switch {
	case OfFile("a/B.vue") != Vue || OfFile("x.unknown") != PHP:
		t.Error("OfFile")
	case !Judges("a.ts") || Judges("a.md"):
		t.Error("Judges")
	}
}
