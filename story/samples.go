package story

var (
	Clean = Python("a clean function", "def total(items):\n    return sum(items)\n")

	ElseAfterRaise = Python("an else after a raise", `class RefundRefused(Exception):
    pass


def refund(order, today) -> int:
    if (today - order.delivered).days > 30:
        raise RefundRefused(order.number)
    else:
        amount = order.total()
        order.mark_refunded(amount)
        return amount
`)

	ElseAfterRaiseFixed = Python("an else after a raise fixed", `class RefundRefused(Exception):
    pass


def refund(order, today) -> int:
    if (today - order.delivered).days > 30:
        raise RefundRefused(order.number)

    amount = order.total()
    order.mark_refunded(amount)
    return amount
`)

	SwallowedException = Python("a swallowed exception", `def run_plugins(plugins, order) -> None:
    for plugin in plugins:
        try:
            plugin.on_checkout(order)
        except Exception:
            continue
`)

	MutableDefault = Python("a mutable default", `def mark(aisle: str, seen: set[str] = set()) -> str:
    seen.add(aisle)
    return aisle
`)

	TwoSinsInOneFile = Python("an else after a raise and a swallowed exception", `def refund(order, today) -> int:
    try:
        if (today - order.delivered).days > 30:
            raise ValueError(order.number)
        else:
            return order.total()
    except Exception:
        return 0
`)

	SixElses = Python("six elses after a raise", `def one(a):
    if a:
        raise ValueError(a)
    else:
        return 1


def two(a):
    if a:
        raise ValueError(a)
    else:
        return 2


def three(a):
    if a:
        raise ValueError(a)
    else:
        return 3


def four(a):
    if a:
        raise ValueError(a)
    else:
        return 4


def five(a):
    if a:
        raise ValueError(a)
    else:
        return 5


def six(a):
    if a:
        raise ValueError(a)
    else:
        return 6
`)

	SettleOriginal = Python("a function settle", `def settle(order, today):
    amount = order.total()
    order.mark_settled(amount, today)
    order.notify_customer(amount)
    return amount
`)

	SettleCopy = Python("a near copy of settle", `def settle_refund(order, today):
    amount = order.total()
    order.mark_settled(amount, today)
    order.notify_customer(amount)
    return amount
`)

	BrokenSyntax = Python("a file that does not parse", "def broken(:\n    return\n")

	GeneratedFile = Python("a generated file", "# @code-commandments-generated\n"+`def refund(order, today) -> int:
    if (today - order.delivered).days > 30:
        raise ValueError(order.number)
    else:
        return order.total()
`)

	NullableReturn = PHP("a nullable return", `<?php

final class Orders
{
    public function find(int $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }
}
`)

	NullReturn = CSharp("a null return for not found", `namespace Shop;

public class Orders
{
    public Order? Find(int id)
    {
        return null;
    }
}
`)
)
