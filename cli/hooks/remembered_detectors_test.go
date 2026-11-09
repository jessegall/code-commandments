package hooks

import (
	"path/filepath"
	"testing"
)

func TestNoPluginFolderKeepsDetectorMemoriesInMemoryOnly(t *testing.T) {
	if _, keeps := rememberedDetectorsIn("", func(...string) {}); keeps {
		t.Error("with no folder there is no file to keep them in")
	}
}

func TestDetectorMemoriesAreOneFileInThePluginFolder(t *testing.T) {
	folder := t.TempDir()

	file, keeps := rememberedDetectorsIn(folder, func(...string) {})
	if !keeps || filepath.Dir(file.Path) != folder {
		t.Errorf("the memories are kept at %q, and the folder is %q", file.Path, folder)
	}
}
