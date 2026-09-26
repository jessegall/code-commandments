package bridge

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func TestAProjectIsMountedWithEveryProjectItReferences(t *testing.T) {
	solution, _ := filepath.EvalSymlinks(t.TempDir())
	project := writer(t, solution)
	project("tests/App.Tests", `<ItemGroup><ProjectReference Include="..\..\src\App\App.csproj" /></ItemGroup>`, "")
	project("src/App", `<ItemGroup><ProjectReference Include="../Core/Core.csproj" /></ItemGroup>`, "")
	project("src/Core", ``, "")
	project("src/Unrelated", ``, "")

	mounts := readOnly([]string{filepath.Join(solution, "tests/App.Tests")})

	want := []string{solution + "/tests/App.Tests:ro", solution + "/src/App:ro", solution + "/src/Core:ro"}
	if !slices.Equal(mounts, want) {
		t.Errorf("mounts %v, want %v", mounts, want)
	}
}

func TestAReferenceIsReadAtAnyDepthAndInTheMSBuildNamespace(t *testing.T) {
	solution, _ := filepath.EvalSymlinks(t.TempDir())
	project := writer(t, solution)
	project("src/App", `<Choose><When Condition="true"><ItemGroup><ProjectReference Include="../Core/Core.csproj" /></ItemGroup></When></Choose>`, "")
	project("src/Core", `<ItemGroup><ProjectReference Include="../Legacy/Legacy.csproj" /></ItemGroup>`, "http://schemas.microsoft.com/developer/msbuild/2003")
	project("src/Legacy", ``, "")

	mounts := readOnly([]string{filepath.Join(solution, "src/App")})

	want := []string{solution + "/src/App:ro", solution + "/src/Core:ro", solution + "/src/Legacy:ro"}
	if !slices.Equal(mounts, want) {
		t.Errorf("mounts %v, want %v", mounts, want)
	}
}

// writer writes a project under the solution: its folder, the body of its <Project>, and the namespace it declares.
func writer(t *testing.T, solution string) func(folder, body, namespace string) {
	return func(folder, body, namespace string) {
		if err := os.MkdirAll(filepath.Join(solution, folder), 0o755); err != nil {
			t.Fatal(err)
		}
		xmlns := ""
		if namespace != "" {
			xmlns = ` xmlns="` + namespace + `"`
		}
		project := `<Project Sdk="Microsoft.NET.Sdk"` + xmlns + `>` + body + `</Project>`
		if err := os.WriteFile(filepath.Join(solution, folder, filepath.Base(folder)+".csproj"), []byte(project), 0o644); err != nil {
			t.Fatal(err)
		}
	}
}
