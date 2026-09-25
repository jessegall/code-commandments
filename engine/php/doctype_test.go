package php

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestDocTypeReadsEveryDocblockAsPhpDoes(t *testing.T) {
	shop.Parity(t, "doctypes", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		doc, documented := DocComment(node)
		switch ask {
		case "doc":
			if !documented {
				return nil
			}

			return doc.Text
		case "element":
			variable := ""
			if node.Kind() == "Param" {
				variable = node.Name()
			}
			element := ElementNamed(doc.Text, variable)
			if element == "" {
				return map[string]any{"element": nil, "resolved": nil}
			}

			return map[string]any{"element": element, "resolved": Resolve(element, node.Source())}
		case "resolve":
			name := node.Name()
			for i := len(name) - 1; i >= 0; i-- {
				if name[i] == '\\' {
					name = name[i+1:]
					break
				}
			}

			return Resolve(name, node.Source())
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}
