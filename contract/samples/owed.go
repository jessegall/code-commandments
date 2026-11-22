package samples

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Exemption is one row of a language's "Not owed" table: a contract item the language truly cannot have, and why.
type Exemption struct {
	Item   string
	Reason string
	File   string
	Line   int
}

// Debt is what a language owes the contract: every item but its checked exemptions.
type Debt struct {
	Owed       []string
	Exemptions []Exemption
}

func Owed(coverage string, language contract.Language) (Debt, []error) {
	file := filepath.Join(coverage, string(language)+".md")
	text, err := os.ReadFile(file)
	if err != nil {
		return Debt{}, []error{err}
	}

	return OwedFrom(file, text)
}

func OwedFrom(file string, text []byte) (Debt, []error) {
	items := contract.Items()
	var debt Debt
	var faults []error
	rows := map[string]int{}
	owing := true
	for number, line := range strings.Split(string(text), "\n") {
		if strings.HasPrefix(line, "## ") {
			owing = strings.TrimSpace(strings.TrimPrefix(line, "## ")) != "Not owed"
		}
		cells := strings.Split(line, "|")
		if owing || len(cells) < 4 {
			continue
		}
		item := strings.Trim(strings.TrimSpace(cells[1]), "`")
		if !strings.Contains(item, "/") {
			continue
		}
		row := Exemption{Item: item, Reason: strings.TrimSpace(cells[2]), File: file, Line: number + 1}
		switch _, known := slices.BinarySearch(items, item); {
		case !known:
			faults = append(faults, fmt.Errorf("%s:%d: %s is no contract item", file, row.Line, item))
		case rows[item] > 0:
			faults = append(faults, fmt.Errorf("%s:%d: %s is exempt twice, first on line %d", file, row.Line, item, rows[item]))
		case row.Reason == "":
			faults = append(faults, fmt.Errorf("%s:%d: %s is exempt without a reason", file, row.Line, item))
		default:
			rows[item] = row.Line
			debt.Exemptions = append(debt.Exemptions, row)
		}
	}
	for _, item := range items {
		if rows[item] == 0 {
			debt.Owed = append(debt.Owed, item)
		}
	}
	if len(debt.Owed) == 0 {
		faults = append(faults, fmt.Errorf("%s: every contract item is exempt, so nothing could be proven", file))
	}

	return debt, faults
}
