"""Stamp cards, stamped at the till and closed when full."""


class StampCard:
    def __init__(self) -> None:
        self.stamps = 0
        self.closed = False
        self.reward = ""

    # @fixed FeatureEnvy
    def close_with(self, reward: str) -> None:
        self.closed = True
        self.reward = reward
        self.stamps = 0


class RewardDesk:
    def __init__(self) -> None:
        self.rewards_given = 0

    # @sin FeatureEnvy
    def redeem(self, card: StampCard) -> None:
        card.closed = True
        card.reward = "free coffee"
        card.stamps = 0
        self.rewards_given += 1

    # @fixed FeatureEnvy
    def redeem_card(self, card: StampCard) -> None:
        card.close_with("free coffee")
        self.rewards_given += 1
