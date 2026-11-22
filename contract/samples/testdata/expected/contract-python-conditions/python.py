def check(value, other):
    # @contract kind/logical
    # @contract fact/logical/operator = "and"
    # @contract role/logical/left -> first_test
    # @contract role/logical/right -> other_ref
    # @contract kind/type-test
    # @contract fact/type-test/form = "isinstance"
    # @contract role/type-test/subject -> value_ref
    # @contract role/type-test/testedType -> int_ref
    # @contract role/if/then -> then_block
    # @label first_test type-test 8
    # @label other_ref reference 35
    # @label value_ref reference 19
    # @label int_ref type-expression 26
    if isinstance(value, int) and other:
        # @label then_block block
        return 1
    # @contract fact/type-test/negated = true
    if not isinstance(other, str):
        return 2
    return 0
