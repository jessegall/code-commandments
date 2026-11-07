//go:build story

package settings

import (
	"os"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

func TestMain(m *testing.M) {
	os.Exit(story.Checked(m))
}
