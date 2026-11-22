# @contract fact/function/form = "function"
# @contract role/function/parameters -> [first_p, second_p, rest_p, flag_p, extra_p]
def build(
    # @label first_p parameter
    # @contract fact/parameter/position = "positional"
    first,
    /,
    # @label second_p parameter
    # @contract fact/parameter/position = "either"
    # @contract fact/parameter/evaluated = "definition"
    # @contract role/parameter/default -> second_default
    # @label second_default literal 19
    second: int = 3,
    # @label rest_p parameter
    # @contract fact/parameter/collects = "rest"
    *rest,
    # @label flag_p parameter
    # @contract fact/parameter/position = "keyword"
    flag=False,
    # @label extra_p parameter
    # @contract fact/parameter/collects = "keywords"
    **extra,
):
    return first
