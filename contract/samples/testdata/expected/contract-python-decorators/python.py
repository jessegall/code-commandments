def tag(label):
    def wrap(target):
        return target
    return wrap


# @contract kind/annotation
# @contract role/annotation/callee -> tag_ref
# @contract role/annotation/arguments -> fast_arg
# @contract kind/argument
# @contract role/argument/value -> fast_lit
# @label fast_ann annotation
# @label tag_ref reference 2
# @label fast_arg argument 6
# @label fast_lit literal 6
# @contract kind/function
# @contract role/function/attributes -> fast_ann
@tag("fast")
def run():
    return 1


# @contract kind/type
# @contract role/type/attributes -> tag_ann
@tag # @label tag_ann annotation
class Box:
    pass # @contract kind/no-op
