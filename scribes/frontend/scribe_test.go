package frontend

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	engine "github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/scribes"
)

type everywhere struct{}

func (everywhere) Includes(string) bool { return true }
func (everywhere) IsScoped() bool       { return false }

// project writes files into a fresh folder and answers its real path.
func project(t *testing.T, files map[string]string) string {
	t.Helper()
	dir, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, content := range files {
		path := filepath.Join(dir, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return dir
}

// goRewrites is what the detector's step rewrites in the folder here, keyed by path relative to it.
func goRewrites(t *testing.T, detector detectors.Detector, dir string) map[string]string {
	t.Helper()
	command, err := engine.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	scanner, err := scribes.Serve(command, engine.Over)
	if err != nil {
		t.Fatal(err)
	}
	defer scanner.Close()
	steps := scribes.Steps(catalog.Frontend, scanner, []detectors.Detector{detector})
	if len(steps) != 1 {
		t.Fatalf("no step fixes %T", detector)
	}
	rewrites, err := steps[0].Run(scribes.Pass{Roots: []string{dir}, Scope: everywhere{}, Frozen: scribes.Frozens{scanner}})
	if err != nil {
		t.Fatal(err)
	}
	relative := map[string]string{}
	for path, content := range rewrites.Contents() {
		relative[strings.TrimPrefix(path, dir+"/")] = content
	}

	return relative
}

// phpRewrites is what PHP's frontend detector step for the detector class rewrites in the folder, asked live.
func phpRewrites(t *testing.T, detectorClass, dir string) map[string]string {
	t.Helper()
	probe := `require $argv[1];
$step = new JesseGall\CodeCommandments\Scribes\Frontend\DetectorStep(new $argv[2]());
$out = [];
foreach ($step->run([$argv[3]], JesseGall\CodeCommandments\Cli\Scope\Scope::everything()) as $path => $content) { $out[substr($path, strlen($argv[3]) + 1)] = $content; }
echo json_encode((object) $out);`
	root, _ := filepath.Abs(filepath.Join("..", ".."))
	command := exec.Command("php", "-r", probe, filepath.Join(root, "vendor", "autoload.php"), `JesseGall\CodeCommandments\Detectors\Frontend\`+detectorClass, dir)
	command.Dir = dir
	out, err := command.Output()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}
	want := map[string]string{}
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}

	return want
}

// sameAsPHP runs the detector's step over the files and holds its rewrite byte for byte equal to what PHP's step
// rewrote, as recorded; it answers the rewrite.
func sameAsPHP(t *testing.T, detector detectors.Detector, detectorClass string, files map[string]string) map[string]string {
	t.Helper()
	dir := project(t, files)
	want := phpAnswer(t, detectorClass, files, dir)
	got := goRewrites(t, detector, dir)
	if len(got) != len(want) {
		t.Fatalf("rewrote %v, PHP %v", keys(got), keys(want))
	}
	for path, content := range want {
		if got[path] != content {
			t.Errorf("%s differs:\n--- go\n%s\n--- php\n%s", path, got[path], content)
		}
	}

	return got
}

func keys(files map[string]string) []string {
	var names []string
	for name := range files {
		names = append(names, name)
	}

	return names
}
