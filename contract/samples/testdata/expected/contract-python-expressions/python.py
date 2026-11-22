def label(count, parts, extra, items):
    # @contract kind/binary
    # @contract fact/binary/operator = "add"
    # @contract role/binary/left -> cnt_l
    # @contract role/binary/right -> one_l
    # @contract role/assignment/targets -> tot_ref
    # @contract role/assignment/values -> add_bin
    # @contract fact/assignment/mode = "assign"
    # @label cnt_l reference 13
    # @label one_l literal 21
    # @label tot_ref binding 5
    # @label add_bin binary 13
    total = count + 1
    # @contract kind/unary
    # @contract fact/unary/operator = "negate"
    # @contract role/unary/operand -> neg_op
    # @label neg_op reference 13
    sign = -count
    flag = not count
    # @contract kind/conditional
    # @contract role/conditional/condition -> gt_cmp
    # @contract role/conditional/consequent -> many_lit
    # @contract role/conditional/alternative -> one_lit
    # @contract kind/comparison
    # @contract fact/comparison/operator = "greater"
    # @contract fact/comparison/operators = ["greater"]
    # @contract role/comparison/operands -> [cnt_c, one_c]
    # @contract link/comparison/left -> cnt_c
    # @contract link/comparison/right -> one_c
    # @contract fact/literal/literalKind at 12 = "string"
    # @label gt_cmp comparison 22
    # @label many_lit literal 12
    # @label one_lit literal 37
    # @label cnt_c reference 22
    # @label one_c literal 30
    size = "many" if count > 1 else "one"
    # @contract kind/interpolation
    # @contract role/interpolation/parts -> [p_size, t_colon, p_count, t_items]
    # @contract kind/text at 20
    # @contract fact/text/value at 20 = ": "
    # @contract fact/*/conversion on reference at 23 = "!r"
    # @label p_size reference 15
    # @label t_colon text 20
    # @label p_count reference 23
    # @label t_items text 31
    text = f"{size}: {count!r} items"
    # @contract kind/entry at 15
    # @contract fact/collection/form = "list"
    # @contract role/collection/entries -> [spread_e, one_e]
    # @contract fact/entry/expansion at 15 = "positional"
    # @contract role/entry/value at 15 -> parts_ref
    # @label spread_e entry 15
    # @label one_e entry 23
    # @label parts_ref reference 16
    joined = [*parts, 1]
    # @contract fact/entry/expansion at 15 = "keyed"
    # @contract role/entry/key at 24 -> k_lit
    # @contract role/entry/value at 24 -> v_lit
    # @label k_lit literal 24
    # @label v_lit literal 29
    merged = {**extra, "k": 1}
    del items[0]
    return total, sign, flag, text, joined, merged


def relay(items): # @contract fact/function/generator = true
    # @contract kind/yield
    # @contract fact/yield/delegates = true
    # @contract role/yield/value -> yf_items
    # @contract kind/expression-statement
    # @contract role/expression-statement/expression -> yf
    # @label yf yield
    # @label yf_items reference
    yield from items


async def fetch(source):
    # @contract kind/await
    # @contract role/await/operand -> read_call
    # @contract kind/call
    # @contract role/call/callee -> read_access
    # @contract kind/access
    # @contract role/access/subject -> src
    # @contract role/access/member -> member
    # @contract fact/access/form = "instance"
    # @label read_call call
    # @label read_access access
    # @label src reference 18
    # @label member reference 25
    return await source.read()
