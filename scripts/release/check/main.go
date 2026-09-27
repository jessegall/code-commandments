// Command check vets a release folder the build wrote: every linux binary of the tool is static, no binary — the
// tool's or a C# bridge's — is over its budget in scripts/release/size-budget.json, and SHA256SUMS lists them all. --record writes the sizes measured beside the
// budgets, which only a person raises. Run from the repository root: go run ./scripts/release/check [--record] <dir>.
package main

import (
	"crypto/sha256"
	"debug/elf"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"
)

// budgetFile is where each platform's size budget and last measured size are kept.
const budgetFile = "scripts/release/size-budget.json"

// Budgets are the size each platform's binary may reach, and the size it last measured, in bytes.
type Budgets struct {
	Note     string           `json:"note"`
	Budget   map[string]int64 `json:"budget"`
	Measured map[string]int64 `json:"measured"`
}

func main() {
	arguments := os.Args[1:]
	record := slices.Contains(arguments, "--record")
	arguments = slices.DeleteFunc(arguments, func(argument string) bool { return argument == "--record" })
	if len(arguments) != 1 {
		fmt.Fprintln(os.Stderr, "usage: check [--record] <dir>")
		os.Exit(2)
	}
	problems, err := check(arguments[0], budgetFile, record)
	if err != nil {
		fmt.Fprintln(os.Stderr, "✗", err)
		os.Exit(1)
	}
	for _, problem := range problems {
		fmt.Fprintln(os.Stderr, "✗", problem)
	}
	if len(problems) > 0 {
		os.Exit(1)
	}
	fmt.Println("✓ every binary is within its budget, the linux ones static; SHA256SUMS written")
}

// check vets the binaries in dir against the budgets at budgetPath and writes their SHA256SUMS; recording, it writes
// the sizes measured into the budgets' file. It answers every problem found.
func check(dir, budgetPath string, record bool) ([]string, error) {
	binaries, err := filepath.Glob(filepath.Join(dir, "commandments-*"))
	if err != nil {
		return nil, err
	}
	if len(binaries) == 0 {
		return nil, fmt.Errorf("%s holds no commandments-* binaries", dir)
	}
	bridges, err := filepath.Glob(filepath.Join(dir, "roslyn-bridge-*"))
	if err != nil {
		return nil, err
	}
	binaries = append(binaries, bridges...)
	budgets, err := readBudgets(budgetPath)
	if err != nil {
		return nil, err
	}
	var problems, sums []string
	for _, binary := range binaries {
		platform := platformOf(binary)
		info, err := os.Stat(binary)
		if err != nil {
			return nil, err
		}
		budgets.Measured[platform] = info.Size()
		problems = append(problems, overBudget(platform, info.Size(), budgets, budgetPath)...)
		// A C# bridge is .NET's own executable, never static: its key is its whole name, so only the tool's are asked.
		if linked := dynamicallyLinked(binary); strings.HasPrefix(platform, "linux-") && linked != "" {
			problems = append(problems, fmt.Sprintf("%s is not static: %s", platform, linked))
		}
		sum, err := sha256Of(binary)
		if err != nil {
			return nil, err
		}
		sums = append(sums, sum+"  "+filepath.Base(binary))
	}
	if err := os.WriteFile(filepath.Join(dir, "SHA256SUMS"), []byte(strings.Join(sums, "\n")+"\n"), 0o644); err != nil {
		return nil, err
	}
	if record {
		return problems, writeBudgets(budgetPath, budgets)
	}

	return problems, nil
}

// overBudget is the problem with a binary's size: a platform with no budget, or one it has outgrown.
func overBudget(platform string, size int64, budgets Budgets, budgetPath string) []string {
	budget, set := budgets.Budget[platform]
	if !set {
		return []string{fmt.Sprintf("%s has no size budget in %s", platform, budgetPath)}
	}
	if size > budget {
		return []string{fmt.Sprintf("%s is %s, over its budget of %s", platform, mebibytes(size), mebibytes(budget))}
	}

	return nil
}

// platformOf is the key a binary's budget is kept under: the os-arch of the tool's own (commandments-linux-amd64 →
// linux-amd64), the whole name of a C# bridge's (roslyn-bridge-linux-amd64).
func platformOf(binary string) string {
	return strings.TrimSuffix(strings.TrimPrefix(filepath.Base(binary), "commandments-"), ".exe")
}

// dynamicallyLinked says why an ELF binary is not static — the interpreter it asks for, or a library it needs — and
// nothing for a static one.
func dynamicallyLinked(binary string) string {
	file, err := elf.Open(binary)
	if err != nil {
		return "not an ELF file: " + err.Error()
	}
	defer file.Close()
	for _, program := range file.Progs {
		if program.Type == elf.PT_INTERP {
			return "it asks for a dynamic loader"
		}
	}
	if libraries, _ := file.ImportedLibraries(); len(libraries) > 0 {
		return "it needs " + strings.Join(libraries, ", ")
	}

	return ""
}

func sha256Of(path string) (string, error) {
	content, err := os.ReadFile(path)
	if err != nil {
		return "", err
	}
	sum := sha256.Sum256(content)

	return hex.EncodeToString(sum[:]), nil
}

func mebibytes(size int64) string {
	return fmt.Sprintf("%.1f MiB", float64(size)/(1<<20))
}

func readBudgets(path string) (Budgets, error) {
	budgets := Budgets{Budget: map[string]int64{}, Measured: map[string]int64{}}
	raw, err := os.ReadFile(path)
	if err != nil {
		return budgets, err
	}
	if err := json.Unmarshal(raw, &budgets); err != nil {
		return budgets, fmt.Errorf("%s: %w", path, err)
	}
	if budgets.Measured == nil {
		budgets.Measured = map[string]int64{}
	}

	return budgets, nil
}

func writeBudgets(path string, budgets Budgets) error {
	raw, err := json.MarshalIndent(budgets, "", "    ")
	if err != nil {
		return err
	}

	return os.WriteFile(path, append(raw, '\n'), 0o644)
}
