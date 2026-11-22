package bridge

import "testing"

func TestTheCSharpBridgeInDockerIsNamedByTheImageOfItsSources(t *testing.T) {
	t.Setenv(roslynVariable, "docker")

	if identity := roslynIdentity(); identity != RoslynImage() {
		t.Errorf("the C# bridge in docker is named %q, not by its image %q, so a rebuilt bridge serves the trees the old one read", identity, RoslynImage())
	}
}
