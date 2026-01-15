<?php

declare(strict_types=1);

namespace Cart\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\Contracts\PriceResolver;
use Cart\Exceptions\UnresolvablePriceException;
use Cart\ResolvedPrice;
use Illuminate\Database\Eloquent\Model;

/**
 * Default price resolver that uses the Priceable interface on buyable models.
 */
class BuyablePriceResolver implements PriceResolver
{
    /**
     * {@inheritdoc}
     */
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        $model = $item->model();

        if ($model === null) {
            throw UnresolvablePriceException::modelNotFound(
                $item->rowId,
                $item->buyableType ?? 'unknown',
                $item->buyableId ?? 'unknown'
            );
        }

        if (!$model instanceof Priceable) {
            throw UnresolvablePriceException::notPriceable(
                $item->rowId,
                $item->buyableType ?? get_class($model)
            );
        }

        return new ResolvedPrice(
            unitPrice: $model->getBuyablePrice($context),
            originalPrice: $model->getBuyableOriginalPrice(),
            priceSource: 'buyable',
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

        // Group items by buyable type for batch loading
        $grouped = $items->groupByBuyableType();

        foreach ($grouped as $buyableType => $typeItems) {
            $buyableTypeStr = (string) $buyableType;
            if (!class_exists($buyableTypeStr)) {
                // Handle items without valid buyable type
                foreach ($typeItems as $item) {
                    throw UnresolvablePriceException::forItem(
                        $item->rowId,
                        $buyableTypeStr,
                        $item->buyableId
                    );
                }

                continue;
            }

            // Batch load all models of this type
            $ids = $typeItems->pluck('buyableId')->filter()->unique()->values()->all();

            if (empty($ids)) {
                continue;
            }

            /** @var class-string<Buyable&Model> $buyableTypeStr */
            $models = $buyableTypeStr::whereIn('id', $ids)->get()->keyBy(
                fn (Model $model): int|string => $model instanceof Buyable ? $model->getBuyableIdentifier() : $model->getKey()
            );

            // Resolve prices for each item
            foreach ($typeItems as $item) {
                $model = $models->get($item->buyableId);

                if ($model === null) {
                    throw UnresolvablePriceException::modelNotFound(
                        $item->rowId,
                        $buyableTypeStr,
                        $item->buyableId ?? 'unknown'
                    );
                }

                if (!$model instanceof Priceable) {
                    throw UnresolvablePriceException::notPriceable(
                        $item->rowId,
                        $buyableTypeStr
                    );
                }

                // Cache the model on the item (model implements Buyable via class constraint)
                /** @var Buyable&Priceable $buyableModel */
                $buyableModel = $model;
                $item->setModel($buyableModel);

                $results[$item->rowId] = new ResolvedPrice(
                    unitPrice: $model->getBuyablePrice($context),
                    originalPrice: $model->getBuyableOriginalPrice(),
                    priceSource: 'buyable',
                );
            }
        }

        return $results;
    }
}
