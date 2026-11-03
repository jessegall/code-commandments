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
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/service"
	"github.com/jessegall/code-commandments/cli/source"
)

// missing is a reach whose bridge cannot run on this machine at all, as the PHP one cannot without php and the
// frontend one cannot without node.
type missing struct{}

// Open is never asked of it: a service asks Own.
func (missing) Open(scan.Bridge, []string) (bridge.Asker, error) {
	return nil, bridge.ToolchainMissing{Language: "PHP", Need: "php on the PATH"}
}

// Own answers that the toolchain is missing, which is what a bridge whose tool is not installed answers.
func (missing) Own(scan.Bridge, []string) (bridge.Asker, bool, error) {
	return nil, true, bridge.ToolchainMissing{Language: "PHP", Need: "php on the PATH"}
}

// TestAServiceWhoseToolIsNotInstalledStaysUpAndSaysWhy holds the service to what a service is FOR. A bridge that
// cannot run here at all is not a fault to retry: the journal restarts a service that ends, `restart: always`, and
// a machine with no php will have no php for the whole session, so the service failed over and over, was recorded
// as a fault and listed as stopped, while its has- verb kept passing because the project really did have PHP. Every
// run of the tool then paid the bridge's whole start-up, seconds of it on a large tree, with nothing saying why.
//
// So it says what is missing and waits to be stopped, as a service with no language to judge already did, and the
// journal's health question is answered from a listener of its own all the while.
func TestAServiceWhoseToolIsNotInstalledStaysUpAndSaysWhy(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.php"), []byte("<?php\n\nclass Cart {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	t.Chdir(root)

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

	// The signal is sent once the warning is out, which is after the handler is installed: the service says what
	// is missing between installing it and waiting on it.
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
