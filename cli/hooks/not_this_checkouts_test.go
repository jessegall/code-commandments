package hooks

import (
	"strings"
	"testing"
)

func TestARefusalNeverPrintsABareSlashAsACheckout(t *testing.T) {
	said := notThisCheckouts("/tmp/elsewhere/order.py", "/", "")

	if strings.Contains(said, "lies outside /,") || strings.Contains(said, "outside / ") {
		t.Errorf("the refusal reads %q, which tells a reader nothing and looks like a bug, because it is one", said)
	}

	if !strings.Contains(said, "could not be worked out") {
		t.Errorf("the refusal does not say that the checkout could not be worked out: %s", said)
	}

	if !strings.Contains(said, "/tmp/elsewhere/order.py") {
		t.Errorf("the refusal does not name the path it is about: %s", said)
	}
}

func TestAPathOfAnotherProjectIsToldAsThatRatherThanAsOutsideAFolder(t *testing.T) {
	said := notThisCheckouts("/Users/someone/other/src/a.py", "/Users/someone/other", "/Users/someone/mine")

	if !strings.Contains(said, "another project") {
		t.Errorf("a path of another project reads as %q, where the reader needs to know the service holds a "+
			"different project entirely", said)
	}

	if !strings.Contains(said, "/Users/someone/mine") {
		t.Errorf("the refusal does not name the project this service does hold: %s", said)
	}
}

func TestAPathOutsideTheCheckoutOfTheSameProjectStillSaysSo(t *testing.T) {
	said := notThisCheckouts("/Users/someone/mine/outside/a.py", "/Users/someone/mine/worker-1", "/Users/someone/mine")

	if !strings.Contains(said, "lies outside /Users/someone/mine/worker-1") {
		t.Errorf("a path outside this checkout of the same project reads as %q", said)
	}
}

func TestAServiceRefusesToHoldSomethingThatIsNoProject(t *testing.T) {
	for _, root := range []string{"", "/"} {
		if !noProjectAt(root) {
			t.Errorf("a service would serve %q as a project, and then every question about every path on the "+
				"machine is answered from a tree that belongs to nobody", root)
		}
	}

	if !noProjectAt(t.TempDir()) {
		t.Error("a folder that is no git repository reads as a project a service can hold")
	}
}
