package bridge

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func TestAProjectIsMountedWithEveryProjectItReferences(t *testing.T) {
	solution, _ := filepath.EvalSymlinks(t.TempDir())
	project := func(folder, references string) {
		if err := os.MkdirAll(filepath.Join(solution, folder), 0o755); err != nil {
			t.Fatal(err)
		}
		body := `<Project Sdk="Microsoft.NET.Sdk"><ItemGroup>` + references + `</ItemGroup></Project>`
		if err := os.WriteFile(filepath.Join(solution, folder, filepath.Base(folder)+".csproj"), []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	project("tests/App.Tests", `<ProjectReference Include="..\..\src\App\App.csproj" />`)
	project("src/App", `<ProjectReference Include="../Core/Core.csproj" />`)
	project("src/Core", ``)
	project("src/Unrelated", ``)

	mounts := readOnly([]string{filepath.Join(solution, "tests/App.Tests")})

	want := []string{solution + "/tests/App.Tests:ro", solution + "/src/App:ro", solution + "/src/Core:ro"}
	if !slices.Equal(mounts, want) {
		t.Errorf("mounts %v, want %v", mounts, want)
	}
}
