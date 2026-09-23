# A packing slip rendered as a list of line fragments joined with newlines. Below it, the FIX: the slip
# written once as a template that shows what it prints.
from textwrap import dedent


def slip(order_ref: str, carrier: str, weight_grams: int) -> str:
    # @sin AssembledTemplate
    return "\n".join([
        "PACKING SLIP",
        f"order:   {order_ref}",
        f"carrier: {carrier}",
        f"weight:  {weight_grams} g",
    ])


# @fixed AssembledTemplate
def slip_shown(order_ref: str, carrier: str, weight_grams: int) -> str:
    return dedent(f"""\
        PACKING SLIP
        order:   {order_ref}
        carrier: {carrier}
        weight:  {weight_grams} g""")


# @righteous AssembledTemplate
def pick_list(skus: list[str]) -> str:
    return "\n".join(f"- {sku}" for sku in skus)
