package service_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
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

const servingHelper = "COMMANDMENTS_TEST_SERVING_HELPER"

// sleeping is a reach whose bridge is a process that writes its own number to $BRIDGE_PID and then waits.
type sleeping struct{}

func (sleeping) Open(scan.Bridge, []string) (bridge.Asker, error) {
	return bridge.Serve([]string{"sh", "-c", `echo $$ > "$BRIDGE_PID"; exec sleep 60`})
}

func (sleeping) Own(scan.Bridge, []string) (bridge.Asker, bool, error) {
	asker, err := sleeping{}.Open(scan.Bridge{}, nil)

	return asker, true, err
}

func TestAServingHelper(t *testing.T) {
	if os.Getenv(servingHelper) == "" {
		t.Skip("runs only as the service of the test that removes its plugin folder")
	}

	serve := service.Serve{Bridge: scan.Bridge{
		Kind:      "php",
		Word:      "php",
		Languages: []source.Language{source.PHP},
		Label:     "PHP",
		Reach:     sleeping{},
	}}
	code, _ := serve.Run(&cli.Input{}, cli.Console{Out: os.Stdout, Err: os.Stderr})
	os.Exit(code)
}

func TestAServiceEndsWithItsBridgeWhenItsPluginFolderIsGone(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.php"), []byte("<?php\n\nclass Cart {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	plugin := filepath.Join(root, "plugin")
	if err := os.Mkdir(plugin, 0o755); err != nil {
		t.Fatal(err)
	}
	bridgePid := filepath.Join(root, "bridge.pid")

	command := exec.Command(os.Args[0], "-test.run=^TestAServingHelper$")
	command.Dir = root
	command.Env = append(os.Environ(),
		servingHelper+"=1",
		"JOURNAL_PLUGIN_DIR="+plugin,
		"BRIDGE_PID="+bridgePid,
		"CLAUDE_PROJECT_DIR="+root,
	)
	if err := command.Start(); err != nil {
		t.Fatal(err)
	}
	ended := make(chan error, 1)
	go func() { ended <- command.Wait() }()
	defer command.Process.Kill()

	var bridged int
	waited := time.Now()
	for bridged == 0 {
		if time.Since(waited) > 10*time.Second {
			t.Fatal("the service started no bridge in ten seconds")
		}
		raw, _ := os.ReadFile(bridgePid)
		bridged, _ = strconv.Atoi(strings.TrimSpace(string(raw)))
		time.Sleep(10 * time.Millisecond)
	}

	if err := os.RemoveAll(plugin); err != nil {
		t.Fatal(err)
	}

	select {
	case <-ended:
	case <-time.After(30 * time.Second):
		t.Fatal("the service kept running after its plugin folder was removed")
	}

	waited = time.Now()
	for syscall.Kill(bridged, 0) == nil {
		if time.Since(waited) > 10*time.Second {
			t.Fatal("the bridge outlived the service whose plugin folder was removed")
		}
		time.Sleep(10 * time.Millisecond)
	}
}
