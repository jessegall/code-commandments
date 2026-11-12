package bridge_test

import (
	"os/exec"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

func TestAServedPHPBridgeEndsOnceTheRunThatStartedItIsKilled(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("no PHP to run the bridge with")
	}

	command, err := php.Here().Command()
	if err != nil {
		t.Fatal(err)
	}

	bridgetest.EndsWithItsRun(t, command)
}

func TestAServedFrontendBridgeEndsOnceTheRunThatStartedItIsKilled(t *testing.T) {
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("no node to run the bridge with")
	}

	command, err := frontend.Here().Command()
	if err != nil {
		t.Fatal(err)
	}

	bridgetest.EndsWithItsRun(t, command)
}
