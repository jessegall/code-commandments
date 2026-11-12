// Command check vets a release folder the build wrote: every linux binary of the tool is static, no binary — the tool's or a C# bridge's — is over its budget in scripts/release/size-budget.json, and SHA256SUMS lists them all.
package main

import (
	"crypto/sha256"
	"debug/elf"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strings"
)

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
	toolOnly := slices.Contains(arguments, "--tool-only")
	arguments = slices.DeleteFunc(arguments, func(argument string) bool { return argument == "--record" || argument == "--tool-only" })
	if len(arguments) != 1 {
		fmt.Fprintln(os.Stderr, "usage: check [--record] [--tool-only] <dir>")
		os.Exit(2)
	}
	problems, err := check(arguments[0], budgetFile, record, toolOnly)
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

func check(dir, budgetPath string, record, toolOnly bool) ([]string, error) {
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
		if linked := dynamicallyLinked(binary); strings.HasPrefix(platform, "linux-") && linked != "" {
			problems = append(problems, fmt.Sprintf("%s is not static: %s", platform, linked))
		}
		sum, err := sha256Of(binary)
		if err != nil {
			return nil, err
		}
		sums = append(sums, sum+"  "+filepath.Base(binary))
	}
	problems = append(problems, missing(dir, budgets, toolOnly)...)
	if err := os.WriteFile(filepath.Join(dir, "SHA256SUMS"), []byte(strings.Join(sums, "\n")+"\n"), 0o644); err != nil {
		return nil, err
	}
	if record {
		return problems, writeBudgets(budgetPath, budgets)
	}

	return problems, nil
}

func missing(dir string, budgets Budgets, toolOnly bool) []string {
	var problems []string
	for _, key := range slices.Sorted(maps.Keys(budgets.Budget)) {
		name := "commandments-" + key
		if strings.HasPrefix(key, "roslyn-bridge-") {
			if toolOnly {
				continue
			}
			name = key
		}
		if _, err := os.Stat(filepath.Join(dir, name)); err != nil {
			problems = append(problems, name+" is budgeted but missing from the release")
		}
	}

	return problems
}

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

func platformOf(binary string) string {
	return strings.TrimPrefix(filepath.Base(binary), "commandments-")
}

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
