package gotree

import (
	"testing"
	"testing/fstest"
)

func TestAChangedBridgeIsAnotherIdentityAndATestFileIsNot(t *testing.T) {
	before := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n")}, "kinds.go": {Data: []byte("package gotree\n")}})
	changed := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n\nconst x = 1\n")}, "kinds.go": {Data: []byte("package gotree\n")}})
	tested := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n")}, "kinds.go": {Data: []byte("package gotree\n")}, "kept_test.go": {Data: []byte("package gotree\n")}})

	if before == changed {
		t.Error("a bridge whose code changed keeps the identity its kept lines were written under, so it serves stale trees")
	}
	if before != tested {
		t.Error("a changed test file changed the bridge's identity, which starts every kept line over for nothing")
	}
	if bridgeIdentity == "" {
		t.Error("the bridge has no identity of its own code")
	}
}
