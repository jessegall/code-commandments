# Moving stock between warehouses walks every site, every bin and every item in it — and decides on
# each item four choices deep.


def rebalance(sites, threshold):
    moved = 0
    for site in sites:
        if site.open:
            for bin_ in site.bins:
                # @sin DeepNesting
                for item in bin_.items:
                    if item.count > threshold:
                        site.transfer(item, item.count - threshold)
                        moved += 1
    return moved
