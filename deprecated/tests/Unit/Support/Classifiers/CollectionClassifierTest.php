<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Classifiers;

use JesseGall\CodeCommandments\Support\Classifiers\CollectionClassifier;
use JesseGall\CodeCommandments\Support\Classifiers\TypeClassifier;
use PHPUnit\Framework\TestCase;

class CollectionClassifierTest extends TestCase
{
    public function test_matches_known_collection_types_by_short_name_without_an_index(): void
    {
        $c = CollectionClassifier::make();

        $this->assertTrue($c->matches('Illuminate\\Support\\Collection'));
        $this->assertTrue($c->matches('Illuminate\\Database\\Eloquent\\Collection'));
        $this->assertTrue($c->matches('Spatie\\LaravelData\\DataCollection'));
        $this->assertTrue($c->matches('App\\Support\\Fluent'));   // short name 'Fluent'
        $this->assertFalse($c->matches('App\\Services\\ReportService'));
        $this->assertFalse($c->matches('stdClass'));
    }

    public function test_make_returns_an_instance(): void
    {
        $this->assertInstanceOf(CollectionClassifier::class, CollectionClassifier::make());
    }

    public function test_anyOf_matches_when_any_member_matches(): void
    {
        $compound = TypeClassifier::anyOf(CollectionClassifier::make(), CollectionClassifier::make());

        $this->assertInstanceOf(TypeClassifier::class, $compound);
        $this->assertTrue($compound->matches('Illuminate\\Support\\LazyCollection'));
        $this->assertFalse($compound->matches('stdClass'));
    }

    public function test_allOf_matches_only_when_all_match(): void
    {
        $both = CollectionClassifier::make()->and(CollectionClassifier::make());
        $this->assertTrue($both->matches('Illuminate\\Support\\Collection'));
        $this->assertFalse($both->matches('stdClass'));
    }
}
