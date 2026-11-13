package service_test

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/hooks"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/service"
	"github.com/jessegall/code-commandments/cli/source"
)

// missing is a reach whose bridge cannot run on this machine at all, as the PHP one cannot without php and the frontend one cannot without node.
type missing struct{}

func (missing) Open(scan.Bridge, []string) (bridge.Asker, error) {
	return nil, bridge.ToolchainMissing{Language: "PHP", Need: "php on the PATH"}
}

func (missing) Own(scan.Bridge, []string) (bridge.Asker, bool, error) {
	return nil, true, bridge.ToolchainMissing{Language: "PHP", Need: "php on the PATH"}
}

func TestAServiceWhoseToolIsNotInstalledStaysUpAndSaysWhy(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.php"), []byte("<?php\n\nclass Cart {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	chdir(t, root)

	serve := service.Serve{Bridge: scan.Bridge{
		Kind:      "php",
		Word:      "php",
		Languages: []source.Language{source.PHP},
		Label:     "PHP",
		Reach:     missing{},
	}}

	var out, errs bytes.Buffer
	answered := make(chan int, 1)
	go func() {
		code, _ := serve.Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs})
		answered <- code
	}()

	waited := time.Now()
	for !strings.Contains(errs.String(), "php on the PATH") {
		if time.Since(waited) > 10*time.Second {
			t.Fatalf("the service said nothing about the missing tool in ten seconds: %q %q", out.String(), errs.String())
		}
		time.Sleep(10 * time.Millisecond)
	}

	if err := syscall.Kill(os.Getpid(), syscall.SIGTERM); err != nil {
		t.Fatal(err)
	}

	select {
	case code := <-answered:
		if code != 0 {
			t.Errorf("the service ended with %d, so the journal starts it again for a tool that will not appear", code)
		}
	case <-time.After(10 * time.Second):
		t.Error("the service did not end when it was stopped")
	}

	if !strings.Contains(errs.String(), "is not judged; everything else is") {
		t.Errorf("the service does not say what is and is not judged: %q", errs.String())
	}
}

func TestAServiceWhoseToolIsNotInstalledTellsTheJournal(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.php"), []byte("<?php\n\nclass Cart {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	chdir(t, root)

	data := t.TempDir()
	t.Setenv("JOURNAL_PLUGIN_DATA", data)
	t.Setenv("JOURNAL_ENV", "a-session-of-its-own")

	serve := service.Serve{Bridge: scan.Bridge{
		Kind:      "php",
		Word:      "php",
		Languages: []source.Language{source.PHP},
		Label:     "PHP",
		Reach:     missing{},
	}}

	var out, errs bytes.Buffer
	answered := make(chan int, 1)
	go func() {
		code, _ := serve.Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs})
		answered <- code
	}()

	queue, _ := hooks.Queue4In(data)
	queued := queue.Path()
	waited := time.Now()
	var told []byte
	for told == nil {
		if time.Since(waited) > 10*time.Second {
			t.Fatalf("the service told the journal nothing in ten seconds: %q", errs.String())
		}
		files, _ := filepath.Glob(filepath.Join(queued, "*.json"))
		for _, file := range files {
			told, _ = os.ReadFile(file)
		}
		time.Sleep(10 * time.Millisecond)
	}

	syscall.Kill(os.Getpid(), syscall.SIGTERM)
	<-answered

	for _, wanted := range []string{"php on the PATH", "a-session-of-its-own", "not a clean answer, it is a smaller one"} {
		if !strings.Contains(string(told), wanted) {
			t.Errorf("what was told the journal does not carry %q: %s", wanted, told)
		}
	}
}

func chdir(t *testing.T, folder string) {
	t.Helper()

	previous, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Chdir(folder); err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { os.Chdir(previous) })
}
