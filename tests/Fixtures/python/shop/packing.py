# Packing decides per line by its kind — inside a loop over lines, inside a loop over orders, inside
# a retry loop: the dispatch sits four choices deep.


def pack(orders, attempts):
    while attempts:
        for order in orders:
            for line in order.lines:
                # @sin DeepNesting
                match line.kind:
                    case "box":
                        order.box(line)
                    case "pallet":
                        order.palletise(line)
        attempts -= 1
