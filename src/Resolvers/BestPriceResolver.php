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
 * Tries all resolvers and returns the lowest price (best for customer).
 */
class BestPriceResolver implements PriceResolver
{
    /**
     * @var array<int, PriceResolver>
     */
    protected array $resolvers;

    /**
     * @param  array<int, PriceResolver>  $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * Add a resolver.
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
        $prices = [];

        foreach ($this->resolvers as $resolver) {
            try {
                $prices[] = $resolver->resolve($item, $context);
            } catch (UnresolvablePriceException) {
                continue;
            }
        }

        if (empty($prices)) {
            throw UnresolvablePriceException::forItem(
                $item->rowId,
                $item->buyableType,
                $item->buyableId
            );
        }

        // Return the price with the lowest unit price
        return $this->selectBestPrice($prices);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        // Collect all prices from all resolvers
        /** @var array<string, array<int, ResolvedPrice>> $pricesByRowId */
        $pricesByRowId = [];

        foreach ($items as $item) {
            $pricesByRowId[$item->rowId] = [];
        }

        foreach ($this->resolvers as $resolver) {
            try {
                $resolved = $resolver->resolveMany($items, $context);

                foreach ($resolved as $rowId => $price) {
                    $pricesByRowId[$rowId][] = $price;
                }
            } catch (UnresolvablePriceException) {
                // Try individual resolution for this resolver
                foreach ($items as $item) {
                    try {
                        $pricesByRowId[$item->rowId][] = $resolver->resolve($item, $context);
                    } catch (UnresolvablePriceException) {
                        continue;
                    }
                }
            }
        }

        // Select best price for each item
        $results = [];

        foreach ($pricesByRowId as $rowId => $prices) {
            if (empty($prices)) {
                $item = $items->get($rowId);
                throw UnresolvablePriceException::forItem(
                    $rowId,
                    $item?->buyableType,
                    $item?->buyableId
                );
            }

            $results[$rowId] = $this->selectBestPrice($prices);
        }

        return $results;
    }

    /**
     * Select the best price (lowest) from a list of resolved prices.
     *
     * @param  array<int, ResolvedPrice>  $prices
     */
    protected function selectBestPrice(array $prices): ResolvedPrice
    {
        usort($prices, fn (ResolvedPrice $a, ResolvedPrice $b) => $a->unitPrice <=> $b->unitPrice);

        return $prices[0];
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
