class Point:
    pass


def describe(shape, flag):
    # @contract kind/dispatch
    # @contract fact/dispatch/form = "match"
    # @contract role/dispatch/subject -> shape_ref
    # @contract role/dispatch/arms -> [arm1, arm2, arm3, arm4, arm5, arm6]
    # @label shape_ref reference 11
    match shape:
        # @contract kind/arm
        # @contract role/arm/body -> small_body
        case 1 | 2: # @label arm1 arm
            return "small" # @label small_body block
        case [first, *rest]: # @label arm2 arm
            return first
        # @contract role/arm/patterns -> seq_pat
        # @contract kind/pattern at 14
        # @contract fact/pattern/form at 14 = "sequence"
        # @contract role/pattern/subpatterns at 14 -> [wild_pat, sec_pat]
        # @contract fact/pattern/form at 15 = "wildcard"
        # @contract fact/pattern/form at 18 = "binding"
        # @contract role/pattern/bindings at 18 -> sec_b
        # @label arm3 arm
        # @label seq_pat pattern 14
        # @label wild_pat pattern 15
        # @label sec_pat pattern 18
        # @label sec_b binding 18
        case (_, second):
            return second
        case {"kind": kind, **others}: # @label arm4 arm
            return kind
        # @contract role/arm/guard -> guard_ref
        # @label arm5 arm
        # @label guard_ref reference 28
        case Point(x=0) if flag:
            return "origin"
        # @contract fact/arm/default = true
        # @label arm6 arm
        case _:
            return None
