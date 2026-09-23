<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\NearDuplicateElementDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use PHPUnit\Framework\TestCase;

final class NearDuplicateElementDetectorTest extends TestCase
{
    public function test_flags_blocks_with_one_skeleton_that_bind_different_data(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div>
                <article class="card">
                  <header class="card-head"><h3>{{ order.number }}</h3><span :class="order.state">{{ order.state }}</span></header>
                  <p class="card-body">{{ order.note }}</p>
                  <footer class="card-foot"><button @click="openOrder(order)">Open</button></footer>
                </article>
                <article class="card">
                  <header class="card-head"><h3>{{ invoice.number }}</h3><span :class="invoice.state">{{ invoice.state }}</span></header>
                  <p class="card-body">{{ invoice.note }}</p>
                  <footer class="card-foot"><button @click="openInvoice(invoice)">Open</button></footer>
                </article>
              </div>
            </template>
            VUE);

        $this->assertSame(['article', 'article'], $this->tags($found));
    }

    public function test_leaves_byte_identical_blocks_to_the_exact_detector(): void
    {
        $block = '<section class="panel"><h2>Totals</h2><dl><dt>Net</dt><dd>{{ net }}</dd></dl><p class="hint">Incl. tax</p></section>';

        $this->assertSame([], $this->find("<template><div>{$block}{$block}</div></template>"));
    }

    public function test_leaves_blocks_whose_skeletons_differ(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div>
                <article class="card"><header><h3>{{ a }}</h3></header><p>{{ b }}</p><footer><button>Open</button></footer></article>
                <article class="card"><header><h3>{{ a }}</h3></header><ul><li>{{ b }}</li></ul><footer><a href="#">Open</a></footer></article>
              </div>
            </template>
            VUE);

        $this->assertSame([], $found);
    }

    public function test_leaves_small_blocks_that_share_a_skeleton_by_coincidence(): void
    {
        $this->assertSame([], $this->find(<<<'VUE'
            <template>
              <div>
                <label class="field"><span>Name</span><input v-model="name" /></label>
                <label class="field"><span>Email</span><input v-model="email" /></label>
              </div>
            </template>
            VUE));
    }

    public function test_reports_the_outermost_repeated_skeleton_only(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div>
                <section class="group"><h2>{{ a.title }}</h2><article class="row"><h3>{{ a.x }}</h3><p>{{ a.y }}</p><footer><button>Go</button></footer></article></section>
                <section class="group"><h2>{{ b.title }}</h2><article class="row"><h3>{{ b.x }}</h3><p>{{ b.y }}</p><footer><button>Go</button></footer></article></section>
              </div>
            </template>
            VUE);

        $this->assertSame(['section', 'section'], $this->tags($found));
    }

    /**
     * @return list<ElementMatch>
     */
    private function find(string $vue): array
    {
        return new NearDuplicateElementDetector()->find(Codebase::fromString($vue));
    }

    /**
     * @param  list<ElementMatch>  $found
     * @return list<string>
     */
    private function tags(array $found): array
    {
        return array_map(static fn (ElementMatch $match): string => $match->tag, $found);
    }
}
