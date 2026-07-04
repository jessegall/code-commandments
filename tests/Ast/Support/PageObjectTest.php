<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\Support\PageObject;
use JesseGall\CodeCommandments\Ast\Support\ResponseSurface;
use PHPUnit\Framework\TestCase;

final class PageObjectTest extends TestCase
{
    /**
     * A page object = a `Data` that composes >=2 nested Data AND travels back in a response. The
     * decorator predicate must accept exactly the page object and reject the look-alikes: a leaf DTO
     * (composes none), a thin wrapper (composes one), and a big internal aggregate never returned.
     */
    public function test_is_page_object_accepts_only_composed_response_bound_data(): void
    {
        $codebase = Codebase::fromString($this->app(), '/proj/app/Ui.php');

        $flagged = $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $n): bool => $n->isPageObject())
            ->get();

        $names = array_map(static fn ($match): ?string => $match->enclosingClassName(), $flagged);

        $this->assertEqualsCanonicalizing(['App\\ShellPage', 'App\\OrdersPage'], $names);
    }

    public function test_composition_requires_more_than_one_nested_data(): void
    {
        $codebase = Codebase::fromString($this->app(), '/proj/app/Ui.php');
        $shape = \JesseGall\CodeCommandments\Ast\Support\DataClassShape::forCodebase($codebase);

        $this->assertTrue($shape->composesMultipleData('App\\ShellPage', $codebase), 'canvas + edges');
        $this->assertFalse($shape->composesMultipleData('App\\TokenDto', $codebase), 'only scalars');
        $this->assertFalse($shape->composesMultipleData('App\\WrapperDto', $codebase), 'one nested Data');
    }

    public function test_response_surface_sees_every_boundary_not_only_inertia(): void
    {
        $codebase = Codebase::fromString($this->app(), '/proj/app/Ui.php');
        $surface = ResponseSurface::forCodebase($codebase);

        $this->assertTrue($surface->isResponseBound('App\\ShellPage'), 'returned directly (Responsable)');
        $this->assertTrue($surface->isResponseBound('App\\OrdersPage'), 'handed to Inertia::render');
        $this->assertTrue($surface->isResponseBound('App\\TokenDto'), 'nested in a returned array');
        $this->assertFalse($surface->isResponseBound('App\\InternalAggregate'), 'never leaves the backend');
    }

    public function test_page_object_policy_ignores_data_ness(): void
    {
        // PageObject adds only the two page conditions; the Data gate is the caller's precondition. The
        // internal aggregate composes two Data but is never returned, so it fails the response condition.
        $codebase = Codebase::fromString($this->app(), '/proj/app/Ui.php');
        $policy = PageObject::forCodebase($codebase);

        $this->assertTrue($policy->isPageObject('App\\ShellPage'));
        $this->assertFalse($policy->isPageObject('App\\InternalAggregate'));
    }

    /**
     * One file, many classes: two nested Data, a page object returned directly, a page object rendered
     * through Inertia, a leaf DTO, a thin wrapper, an internal aggregate, and the controllers.
     */
    private function app(): string
    {
        return <<<'PHP'
        <?php

        namespace App;

        use Illuminate\Routing\Controller;
        use Inertia\Inertia;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        use Spatie\LaravelData\Data;

        class Canvas extends Data {
            public function __construct(public readonly string $svg) {}
        }

        class Edge extends Data {
            public function __construct(public readonly string $from, public readonly string $to) {}
        }

        class ShellPage extends Data {
            public readonly Canvas $canvas;
            /** @var list<Edge> */
            #[DataCollectionOf(Edge::class)]
            public readonly array $edges;

            public static function for(string $id): static { return static::from(['id' => $id]); }
        }

        class OrdersPage extends Data {
            public readonly Canvas $canvas;
            public readonly Edge $lead;
        }

        class TokenDto extends Data {
            public function __construct(public readonly string $token) {}
        }

        class WrapperDto extends Data {
            public readonly Canvas $canvas;
        }

        class InternalAggregate extends Data {
            public readonly Canvas $canvas;
            public readonly Edge $edge;
        }

        class ShellController extends Controller {
            public function show(string $id): ShellPage {
                return ShellPage::for($id);
            }
            public function receipt(): array {
                return ['token' => TokenDto::from('abc')];
            }
        }

        class OrdersController extends Controller {
            public function index() {
                return Inertia::render('Orders', OrdersPage::from([]));
            }
        }

        class AggregateService {
            public function build(): InternalAggregate {
                return InternalAggregate::from([]);
            }
        }
        PHP;
    }
}
