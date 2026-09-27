package fixture_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
)

// TestAMissingBridgeStopsTheCurriculumNamingIt holds the curriculum to failing where the C# bridge is missing, naming
// how to build its image, before any fixture is read: examples carved without an engine are not ones to publish.
func TestAMissingBridgeStopsTheCurriculumNamingIt(t *testing.T) {
	t.Setenv("PATH", t.TempDir())
	t.Setenv("COMMANDMENTS_ROSLYN", "docker")
	_, err := fixture.Curriculum(t.TempDir())
	if err == nil || !strings.Contains(err.Error(), "docker build -t "+bridge.RoslynImage()) {
		t.Errorf("without docker the curriculum answers %v", err)
	}
}
