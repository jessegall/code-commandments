import enum
from typing import cast


class Suit(enum.Enum):
    HEARTS = "H"
    SPADES = "S"


# @contract kind/alias
# @contract role/alias/typeParameters -> pair_t
# @contract role/alias/underlyingType -> pair_u
# @contract kind/type-parameter
# @label pair_t type-parameter 11
# @label pair_u type-expression 16
type Pair[T] = tuple[T, T]


# @contract role/function/typeParameters -> [t_tp, u_tp]
# @contract role/type-parameter/constraint at 11 -> t_con
# @contract role/type-parameter/default at 19 -> u_def
# @contract fact/type-expression/form at 35 = "named"
# @contract role/type-expression/typeArguments at 35 -> list_arg
# @label t_tp type-parameter 11
# @label u_tp type-parameter 19
# @label t_con type-expression 14
# @label u_def type-expression 23
# @label list_arg type-expression 40
def first[T: int, U = str](items: list[T]) -> T:
    # @contract kind/index
    # @contract role/index/subject -> items_ref
    # @contract role/index/key -> zero_key
    # @label items_ref reference 12
    # @label zero_key literal 18
    return items[0]


# @contract role/type/typeParameters -> box_t
# @label box_t type-parameter 11
class Box[T]:
    pass


def check(value: object, other: object) -> int:
    if isinstance(value, int) and not isinstance(other, (str, bytes)):
        # @contract kind/cast
        # @contract fact/cast/form = "assertion"
        # @contract role/cast/operand -> cast_val
        # @contract role/cast/targetType -> cast_t
        # @label cast_val reference 26
        # @label cast_t type-expression 21
        return cast(int, value)
    return 0
