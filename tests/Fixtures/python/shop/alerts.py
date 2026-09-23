# Stock alerts hook into the shop's event bus.


# @sin NearDuplicateFunction
def register(bus) -> None:
    bus.intercept(WatchKeyword("alerts", whole_words=True))
    bus.handle(RepeatStanding("alerts", every=25))
    bus.handle(MentionInChat("alerts", limit=3))
    bus.log.info("registered %s", "alerts")
