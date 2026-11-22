def build(names):
    # @contract kind/comprehension
    # @contract fact/comprehension/form = "list"
    # @contract role/comprehension/element -> sq_elem
    # @contract role/comprehension/clauses -> [for_clause, if_clause]
    # @contract kind/comprehension-clause at 22
    # @contract fact/comprehension-clause/form at 22 = "for"
    # @contract fact/comprehension-clause/form at 40 = "if"
    # @contract role/comprehension-clause/bindings at 22 -> n_bind
    # @contract role/comprehension-clause/iterable at 22 -> rng_call
    # @contract role/comprehension-clause/condition at 40 -> mod_cmp
    # @label sq_elem binary 16
    # @label for_clause comprehension-clause 22
    # @label if_clause comprehension-clause 40
    # @label n_bind binding 26
    # @label rng_call construction 31
    # @label mod_cmp comparison 43
    squares = [n * n for n in range(5) if n % 2 == 0]
    # @contract fact/comprehension/form = "map"
    # @contract role/comprehension/key -> dkey
    # @contract role/comprehension/value -> dval
    # @label dkey reference 14
    # @label dval call 20
    sizes = {name: len(name) for name in names}
    return squares, sizes


async def collect(source):
    # @contract fact/comprehension-clause/async = true
    return [item async for item in source]
