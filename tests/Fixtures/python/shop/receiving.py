# Look-alikes at the limit. Three choices deep is where the rule stops counting; a `try` and a `with`
# are boundaries the work runs inside, not choices; and an `elif` is a rung of its `if`, not a level.


# @righteous DeepNesting
def receive(deliveries, dock):
    for delivery in deliveries:
        try:
            with dock.lock():
                if delivery.damaged:
                    dock.reject(delivery)
                elif delivery.partial:
                    if delivery.backorder_allowed:
                        dock.backorder(delivery)
                else:
                    dock.accept(delivery)
        except dock.Busy:
            dock.retry_later(delivery)
