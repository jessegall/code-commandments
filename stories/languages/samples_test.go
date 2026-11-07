//go:build story

package languages

import "github.com/jessegall/code-commandments/story"

var (
	notFoundAsNone = story.Python("a function that returns none for not found typed as optional", `from typing import Optional


class Order:
    pass


def find(orders: dict, number: int) -> Optional[Order]:
    if number in orders:
        return orders[number]
    return None
`)

	phpCoalesced = story.PHP("a nullable return with a coalesced default at the call site", `<?php

final class Checkout
{
    public function total(Orders $orders, int $id): int
    {
        return $orders->find($id)?->total() ?? 0;
    }
}
`)

	tsCoalesced = story.Sample{Under: "bridge/mypy", Label: "a typescript module with a coalesced default and an optional field", Text: `export interface Order {
  note?: string
}

export const noteOf = (order: Order) => order.note ?? ''
`}

	vueChain = story.Sample{Under: "bridge/mypy", Label: "a vue component with an if chain on one subject", Text: `<template>
  <p v-if="state === 'a'">a</p>
  <p v-else-if="state === 'b'">b</p>
  <p v-else-if="state === 'c'">c</p>
</template>

<script setup lang="ts">
defineProps<{ state: string }>()
</script>
`}

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

		return text + "export const noteOf = (note?: string) => note ?? ''\n"
	}()}
)
