<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\Exceptions\UnresolvablePriceException;
use Cart\ResolvedPrice;
use Cart\Resolvers\BestPriceResolver;
use PHPUnit\Framework\TestCase;

class BestPriceResolverTest extends TestCase
{
    public function test_it_creates_empty_resolver(): void
    {
        $resolver = new BestPriceResolver;

        $this->assertEmpty($resolver->getResolvers());
    }

    public function test_it_adds_resolvers(): void
    {
        $resolver = new BestPriceResolver;
        $mockResolver = $this->createMock(PriceResolver::class);

        $resolver->add($mockResolver);

        $this->assertCount(1, $resolver->getResolvers());
    }

    public function test_it_selects_lowest_price(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $context = $this->createContext();

        $resolver1 = $this->createFakeResolver(1500);
        $resolver2 = $this->createFakeResolver(1000);
        $resolver3 = $this->createFakeResolver(2000);

        $best = new BestPriceResolver([$resolver1, $resolver2, $resolver3]);
        $result = $best->resolve($item, $context);

        $this->assertSame(1000, $result->unitPrice);
    }

    public function test_it_skips_failing_resolvers(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $context = $this->createContext();

        $resolver1 = $this->createFailingResolver();
        $resolver2 = $this->createFakeResolver(1500);
        $resolver3 = $this->createFakeResolver(1000);

        $best = new BestPriceResolver([$resolver1, $resolver2, $resolver3]);
        $result = $best->resolve($item, $context);

        $this->assertSame(1000, $result->unitPrice);
    }

    public function test_it_throws_when_all_resolvers_fail(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $context = $this->createContext();

        $resolver1 = $this->createFailingResolver();
        $resolver2 = $this->createFailingResolver();

        $best = new BestPriceResolver([$resolver1, $resolver2]);

        $this->expectException(UnresolvablePriceException::class);
        $best->resolve($item, $context);
    }

    public function test_it_throws_when_no_resolvers(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $context = $this->createContext();

        $best = new BestPriceResolver([]);

        $this->expectException(UnresolvablePriceException::class);
        $best->resolve($item, $context);
    }

    public function test_it_resolves_many_with_best_prices(): void
    {
        $item1 = new CartItem(id: 1, quantity: 1);
        $item2 = new CartItem(id: 2, quantity: 1);
        $items = new CartItemCollection([$item1->rowId => $item1, $item2->rowId => $item2]);
        $context = $this->createContext();

        $resolver1 = $this->createFakeResolver(1500);
        $resolver2 = $this->createFakeResolver(1000);

        $best = new BestPriceResolver([$resolver1, $resolver2]);
        $results = $best->resolveMany($items, $context);

        $this->assertCount(2, $results);
        $this->assertSame(1000, $results[$item1->rowId]->unitPrice);
        $this->assertSame(1000, $results[$item2->rowId]->unitPrice);
    }

    public function test_it_returns_empty_array_for_empty_collection(): void
    {
        $items = new CartItemCollection;
        $context = $this->createContext();

        $best = new BestPriceResolver([$this->createFakeResolver(100)]);
        $results = $best->resolveMany($items, $context);

        $this->assertEmpty($results);
    }

    public function test_it_handles_single_resolver(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $context = $this->createContext();

        $resolver = $this->createFakeResolver(999);

        $best = new BestPriceResolver([$resolver]);
        $result = $best->resolve($item, $context);

        $this->assertSame(999, $result->unitPrice);
    }

    private function createContext(): CartContext
    {
        return new CartContext(
            user: null,
            instance: 'default',
            currency: '$',
            locale: 'en',
        );
    }

    private function createFakeResolver(int $price): PriceResolver
    {
        return new class($price) implements PriceResolver
        {
            public function __construct(private int $price) {}

            public function resolve(CartItem $item, CartContext $context): ResolvedPrice
            {
                return new ResolvedPrice($this->price, $this->price);
            }

            public function resolveMany(CartItemCollection $items, CartContext $context): array
            {
                $results = [];
                foreach ($items as $item) {
                    $results[$item->rowId] = new ResolvedPrice($this->price, $this->price);
                }

                return $results;
            }
        };
    }

    private function createFailingResolver(): PriceResolver
    {
        return new class implements PriceResolver
        {
            public function resolve(CartItem $item, CartContext $context): ResolvedPrice
            {
                throw UnresolvablePriceException::forItem($item->rowId, null, null);
            }

            public function resolveMany(CartItemCollection $items, CartContext $context): array
            {
                throw UnresolvablePriceException::forItem('test', null, null);
            }
        };
    }
}
