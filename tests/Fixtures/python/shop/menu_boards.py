"""The café's menu board and the dishes chalked on it."""

from dataclasses import dataclass, field


@dataclass
class Dish:
    name: str = ""
    vegan: bool = False


@dataclass
class MenuBoard:
    dishes: list[Dish] = field(default_factory=list)

    # @fixed FeatureEnvy
    def vegan_names(self) -> list[str]:
        return [dish.name for dish in self.dishes if dish.vegan]


class Chalk:
    def write(self, dish: Dish) -> None:
        pass


class BoardPainter:
    def __init__(self, chalk: Chalk) -> None:
        self.chalk = chalk

    # @sin FeatureEnvy
    def vegan_line(self, board: MenuBoard) -> str:
        return ", ".join(dish.name for dish in board.dishes if dish.vegan)

    # @righteous FeatureEnvy
    def paint(self, board: MenuBoard) -> None:
        for dish in board.dishes:
            self.chalk.write(dish)
