package judge

import (
	"path/filepath"
	"strings"
	"testing"
)

func TestARunRemembersInThePluginFolderWhenThereIsOne(t *testing.T) {
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	remembered, keeps := RememberedFor("/a/project", func(...string) {})
	if !keeps || filepath.Dir(remembered.Memories.Path) != folder || filepath.Dir(remembered.Weighings.Path) != folder {
		t.Errorf("the memories are kept at %q and %q, and the plugin's folder is %q", remembered.Memories.Path, remembered.Weighings.Path, folder)
	}
}

func TestAPlainRunRemembersUnderTheUsersCacheOnePlacePerProject(t *testing.T) {
	cache := t.TempDir()
	t.Setenv(pluginData, "")
	t.Setenv("XDG_CACHE_HOME", cache)

	one, keeps := RememberedFor("/a/project", func(...string) {})
	other, _ := RememberedFor("/another/project", func(...string) {})
	again, _ := RememberedFor("/a/project", func(...string) {})

	if !keeps || !strings.HasPrefix(one.Memories.Path, cache) {
		t.Fatalf("a plain run keeps its memories at %q, outside the cache %q", one.Memories.Path, cache)
	}

	if one.Memories.Path == other.Memories.Path {
		t.Errorf("two projects share the memory file %q", one.Memories.Path)
	}

	if one.Memories.Path != again.Memories.Path {
		t.Errorf("one project remembers in %q and then in %q", one.Memories.Path, again.Memories.Path)
	}
}
