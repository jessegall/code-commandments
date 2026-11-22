# @contract kind/type
# @contract fact/type/form = "class"
# @contract role/type/writtenMembers -> base_label
class Base: # @label base_cls type
    # @contract kind/function
    # @contract fact/function/form = "method"
    # @contract fact/*/effective on function = {"visibility":"public"}
    # @contract role/function/receiver -> self_p
    # @contract role/function/returnType -> str_t
    # @contract role/function/body -> label_body
    # @label base_label function
    # @label self_p parameter 15
    # @label str_t type-expression 24
    def label(self) -> str:
        # @contract kind/return
        # @contract kind/block
        # @contract role/block/statements -> ret_base
        # @label label_body block
        # @label ret_base return
        return "base"


# @contract role/type/bases -> base_ref
# @contract fact/*/refers on type-expression -> base_cls
# @label base_ref type-expression 13
class Child(Base):
    # @contract fact/*/obligations on function = [{"origin":"project"}]
    def label(self) -> str:
        return "child"


# @contract kind/parameter
# @contract kind/binding
# @contract fact/function/form = "function"
# @contract fact/*/name on function = "total"
# @contract fact/*/symbol on function -> total_fn
# @contract role/function/parameters -> rate_p
# @contract role/parameter/binding -> rate_b
# @contract role/parameter/declaredType -> int_t
# @label total_fn function
# @label rate_p parameter 11
# @label rate_b binding 11
# @label int_t type-expression 17
def total(rate: int) -> int:
    # @contract kind/reference
    # @contract link/reference/binding -> rate_b
    # @contract fact/*/resolvedType on reference = {"text":"int"}
    return rate + 1
