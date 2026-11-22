class Point:
    pass


def describe(shape, flag):
    match shape:
        case 1 | 2:
            return "small"
        case [first, *rest]:
            return first
        case (_, second):
            return second
        case {"kind": kind, **others}:
            return kind
        case Point(x=0) if flag:
            return "origin"
        case _:
            return None
