//go:build story

package bridges

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	manifest        = "journal-plugin.5.json"
	couldNotBeRead  = "could not be parsed"
	versionOfBinary = "--version"
)

func aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t *testing.T, broken story.Sample, file string) {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	written := s.Writes(story.ElseAfterRaise, "refund.py")
	ada := s.Agent("Ada")

	ada.Edits(broken, file)

	s.Says(ada.Hears(file), file, couldNotBeRead)
	s.Says(s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(written)), "refund.py")
	s.CostIs(ledger.TreeReadsFailed, 0)
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAPythonFileThatDoesNotParseIsSaidSoAndTheTreeIsKept(t *testing.T) {
	aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t, story.BrokenSyntax, "broken.py")
}

func TestATypeScriptFileThatDoesNotParseIsSaidSoAndTheTreeIsKept(t *testing.T) {
	aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t, story.BrokenTypeScript, "broken.ts")
}

func TestAVueFileThatDoesNotParseIsSaidSoAndTheTreeIsKept(t *testing.T) {
	aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t, story.BrokenVue, "Broken.vue")
}

func TestAPHPFileThatDoesNotParseIsSaidSoAndTheTreeIsKept(t *testing.T) {
	aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t, story.BrokenPHP, "Broken.php")
}

func TestACSharpFileThatDoesNotParseIsSaidSoAndTheTreeIsKept(t *testing.T) {
	if out, err := exec.Command("docker", "info").CombinedOutput(); err != nil {
		t.Skip("C# is read by the Roslyn bridge in its docker image, and docker is not reachable from this run: " + err.Error() + " " + string(out))
	}

	aFileThatDoesNotParseIsSaidSoAndTheRestIsStillJudged(t, story.BrokenCSharp, "Broken.cs")
}

func TestThePluginTheBinaryAndTheManifestNameOneVersion(t *testing.T) {
	s := story.Begin(t)

	raw, err := os.ReadFile(filepath.Join("..", "..", manifest))
	if err != nil {
		s.Fails("the manifest could not be read: " + err.Error())

		return
	}

	var pinned struct {
		Version string `json:"version"`
	}

	if err := json.Unmarshal(raw, &pinned); err != nil || pinned.Version == "" {
		s.Fails(manifest + " names no version")

		return
	}

	s.Says(s.Asks("plugin", "list"), pinned.Version)
	s.Says(s.Asks("commandments", versionOfBinary), pinned.Version)
}
