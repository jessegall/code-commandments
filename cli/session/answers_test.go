package session

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// What the session verbs answer was proved only by the recorded parity cases, which run the built binary. The
// command reads the session's own folder and writes to a console, so its answers are held here: a verb given no
// name, a name nobody has, a name another session already holds, and a shell that has no session of its own.

// asked runs the command inside the project and answers its exit code and what it said.
func asked(t *testing.T, project string, args ...string) (code int, said string, warned string) {
	t.Helper()
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(project); err != nil {
		t.Fatal(err)
	}
	defer os.Chdir(was)

	var out, errs bytes.Buffer
	code, err = Command{}.Run(cli.InputOf("session", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String(), errs.String()
}

// aProject is a project with a git folder, so the session's own folder is found where a session would keep it.
func aProject(t *testing.T) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, ".git"), 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// TestAVerbGivenNoNameSaysWhatToType holds the two verbs that take one: a reader who typed the verb alone is told
// what the command wants, rather than left with an answer about nothing.
func TestAVerbGivenNoNameSaysWhatToType(t *testing.T) {
	project := aProject(t)

	for _, verb := range []string{"name", "forget", "adopt"} {
		t.Run(verb, func(t *testing.T) {
			code, said, warned := asked(t, project, verb)

			if code == 0 {
				t.Errorf("`session %s` with no name answers as if it had one", verb)
			}
			if strings.TrimSpace(said+warned) == "" {
				t.Errorf("`session %s` refuses without saying why", verb)
			}
		})
	}
}

// TestForgettingANameNobodyHasSaysSo holds the answer to a name that was never given: silence would read as having
// forgotten something.
func TestForgettingANameNobodyHasSaysSo(t *testing.T) {
	code, said, warned := asked(t, aProject(t), "forget", "zzzzz")

	if code == 0 {
		t.Error("forgetting a name nobody has answered as if something was forgotten")
	}
	if !strings.Contains(said+warned, "zzzzz") {
		t.Errorf("it says %q, which does not name what was asked for", said+warned)
	}
}

// TestNamingWithoutASessionOfItsOwnSaysSo holds the shell outside the harness: there is no session to name, and the
// command says that rather than naming nothing.
func TestNamingWithoutASessionOfItsOwnSaysSo(t *testing.T) {
	t.Setenv("CLAUDE_CODE_SESSION_ID", "")

	code, said, warned := asked(t, aProject(t), "name", "review")

	if code == 0 {
		t.Error("a shell with no session of its own named one anyway")
	}
	if !strings.Contains(strings.ToLower(said+warned), "session") {
		t.Errorf("it says %q, which does not say there is no session", said+warned)
	}
}

// TestASessionIsNamedAndThenKnownByThatName holds the pair a reader uses: a session takes a name, and the name is
// known afterwards, which is what lets them forget it again. The list is not where that shows, since it holds the
// sessions that have RECORDED something and a freshly named one has recorded nothing.
func TestASessionIsNamedAndThenKnownByThatName(t *testing.T) {
	project := aProject(t)
	t.Setenv("CLAUDE_CODE_SESSION_ID", "61bdc0de")

	code, said, warned := asked(t, project, "name", "review")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if !strings.Contains(said, "review") {
		t.Errorf("it does not say what the session is now called: %q", said)
	}

	forgot, forgotten, _ := asked(t, project, "forget", "review")
	if forgot != 0 {
		t.Errorf("the name just given is not known: forgetting it exits %d", forgot)
	}
	if !strings.Contains(forgotten, "review") {
		t.Errorf("forgetting it says %q, which does not name it", forgotten)
	}
}

// TestANameAnotherSessionHoldsIsRefused holds the one answer that protects a session's own folder: two sessions
// under one name would read each other's findings as their own.
func TestANameAnotherSessionHoldsIsRefused(t *testing.T) {
	project := aProject(t)

	t.Setenv("CLAUDE_CODE_SESSION_ID", "61bdc0de")
	if code, _, warned := asked(t, project, "name", "review"); code != 0 {
		t.Fatalf("the first naming exits %d, saying %q", code, warned)
	}

	t.Setenv("CLAUDE_CODE_SESSION_ID", "9f0a1b2c")
	code, said, warned := asked(t, project, "name", "review")

	if code == 0 {
		t.Error("two sessions were given the one name")
	}
	if !strings.Contains(said+warned, "review") {
		t.Errorf("it says %q, which does not name the name that is taken", said+warned)
	}
}

// TestAdoptingASessionNobodyHasSaysSo holds the answer when the session named is not there: adopting nothing must
// not read as having adopted its findings.
func TestAdoptingASessionNobodyHasSaysSo(t *testing.T) {
	code, said, warned := asked(t, aProject(t), "adopt", "zzzzz")

	if code == 0 {
		t.Errorf("adopting a session nobody has answered as if it had been adopted:\n%s", said)
	}
	if !strings.Contains(warned+said, "zzzzz") {
		t.Errorf("it says %q, which does not name the session asked for", warned+said)
	}
}
