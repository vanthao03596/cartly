<?php

declare(strict_types=1);

namespace Cart\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\Exceptions\UnresolvablePriceException;
use Cart\ResolvedPrice;

/**
 * Chains multiple resolvers, using the first one that successfully resolves.
 */
class ChainPriceResolver implements PriceResolver
{
    /**
     * @var array<int, PriceResolver>
     */
    protected array $resolvers;

    /**
     * @param array<int, PriceResolver> $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * Add a resolver to the chain.
     */
    public function add(PriceResolver $resolver): self
    {
        $this->resolvers[] = $resolver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        $lastException = null;

        foreach ($this->resolvers as $resolver) {
            try {
                return $resolver->resolve($item, $context);
            } catch (UnresolvablePriceException $e) {
                $lastException = $e;

                continue;
            }
        }

        throw $lastException ?? UnresolvablePriceException::forItem(
            $item->rowId,
            $item->buyableType,
            $item->buyableId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $results = [];
        $remaining = new CartItemCollection($items->all());

        foreach ($this->resolvers as $resolver) {
            if ($remaining->isEmpty()) {
                break;
            }

            try {
                $resolved = $resolver->resolveMany($remaining, $context);

                foreach ($resolved as $rowId => $price) {
                    $results[$rowId] = $price;
                    $remaining->forget($rowId);
                }
            } catch (UnresolvablePriceException) {
                // Continue to next resolver
                continue;
            }
        }

        // If any items remain unresolved, throw exception
        if ($remaining->isNotEmpty()) {
            $firstUnresolved = $remaining->first();
            throw UnresolvablePriceException::forItem(
                $firstUnresolved->rowId,
                $firstUnresolved->buyableType,
                $firstUnresolved->buyableId
            );
        }

        return $results;
    }

    /**
     * Get all resolvers.
     *
     * @return array<int, PriceResolver>
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }
}
