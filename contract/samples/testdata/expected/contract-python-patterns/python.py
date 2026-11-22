def kind(value):
    match value:
        # @contract kind/pattern
        # @contract fact/pattern/form = "value"
        # @contract role/pattern/value -> one_lit
        # @label one_lit literal 14
        case 1:
            return "one"
        # @contract role/pattern/constrainedType -> str_t
        # @label str_t type-expression 14
        case str():
            return "text"
