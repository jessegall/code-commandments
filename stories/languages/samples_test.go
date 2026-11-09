//go:build story

package languages

import "github.com/jessegall/code-commandments/story"

var (
	typedElseAfterRaise = story.Python("a typed lookup with an else after a raise", `from typing import Optional


class Order:
    pass


def find(orders: dict, number: int) -> Optional[Order]:
    if number not in orders:
        raise KeyError(number)
    else:
        return orders[number]
`)

	phpDeepNesting = story.PHPDeepNesting

	tsMutatedLoop = story.TSMutatedLoop

	vueChain = story.VueChain

	csharpNull = story.NullReturn

	csharpOther = story.CSharp("a second class returning null for not found", `namespace Shop;

public class Customers
{
    public Customer? Find(int id)
    {
        return null;
    }
}
`)

	goFlow = story.Sample{Under: "bridge/mypy", Label: "a go function with an error check wrapping the whole body", Text: `package shop

func Refund(order *Order) error {
	if order != nil {
		order.Refund()
		order.Notify()
		return nil
	} else {
		return errNoOrder
	}
}
`}

	goSibling = story.Sample{Under: "bridge/mypy", Label: "a go sibling file with a sin of its own", Text: `package shop

func Notify(order *Order) error {
	if order != nil {
		order.Notify()
		return nil
	} else {
		return errNoOrder
	}
}
`}

	goClean = story.Sample{Under: "bridge/mypy", Label: "a clean go file in the same package", Text: "package shop\n\nfunc Total(order *Order) int {\n\treturn order.Total()\n}\n"}

	goDuplicate = story.Sample{Under: "bridge/mypy", Label: "a go function that nearly duplicates one in its package", Text: `package shop

func RefundAgain(order *Order) error {
	if order != nil {
		order.Refund()
		order.Notify()
		return nil
	} else {
		return errNoOrder
	}
}
`}

	tsFormatOne = story.Sample{Under: "bridge/mypy", Label: "a typescript format function in one module", Text: `export function format(order: { total: number }): string {
  const amount = order.total.toFixed(2)
  return 'EUR ' + amount + ' total'
}
`}

	tsFormatTwo = story.Sample{Under: "bridge/mypy", Label: "a near copy of the typescript format function", Text: `export function formatLabel(order: { total: number }): string {
  const amount = order.total.toFixed(2)
  return 'EUR ' + amount + ' label'
}
`}

	tsMirror = story.Sample{Under: "bridge/mypy", Label: "a typescript interface that restates a server data class", Text: `export interface OrderData {
  id: number
  total: number
}
`}

	rubyFile = story.Sample{Under: "bridge/mypy", Label: "a ruby file shaped like a sin", Text: "def refund(order)\n  if order.late?\n    raise 'late'\n  else\n    order.total\n  end\nend\n"}

	tsAfterImports = story.Sample{Under: "bridge/mypy", Label: "a typescript file with the sin after forty lines of imports", Text: func() string {
		text := ""
		for range 40 {
			text += "import { a } from './a'\n"
		}

		return text + "export function drain(items: string[]): void { for (const item of items) { items.shift() } }\n"
	}()}
)
