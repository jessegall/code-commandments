def tag(label):
    def wrap(target):
        return target
    return wrap


@tag("fast")
def run():
    return 1


@tag
class Box:
    pass
