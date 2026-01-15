<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\Cart;
use Cart\CartContent;
use Cart\CartInstance;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\CartContext;
use Cart\Contracts\PriceResolver;
use Cart\Drivers\ArrayDriver;
use Cart\ResolvedPrice;
use Cart\Tests\Stubs\BuyableProduct;
use Cart\Tests\Stubs\BuyableService;
use Cart\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ModelLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        // Create products table
        $this->app['db']->connection()->getSchemaBuilder()->create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('price');
        });

        // Create services table
        $this->app['db']->connection()->getSchemaBuilder()->create('services', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('price');
        });
    }

    public function test_has_model_loaded_returns_false_when_no_model(): void
    {
        $item = new CartItem(id: 1, quantity: 1);

        $this->assertFalse($item->hasModelLoaded());
    }

    public function test_has_model_loaded_returns_true_after_set_model(): void
    {
        $product = BuyableProduct::create(['name' => 'Test', 'price' => 1000]);
        $item = CartItem::fromBuyable($product);
        $item->setModel($product);

        $this->assertTrue($item->hasModelLoaded());
    }

    public function test_model_loading_callback_is_triggered_on_first_access(): void
    {
        $callbackTriggered = false;
        $product = BuyableProduct::create(['name' => 'Test', 'price' => 1000]);

        // fromBuyable() does NOT call setModel(), so model is not loaded
        $item = CartItem::fromBuyable($product);

        $item->setModelLoadingCallback(function () use (&$callbackTriggered) {
            $callbackTriggered = true;
        });

        // Accessing model() should trigger the callback
        $item->model();

        $this->assertTrue($callbackTriggered);
    }

    public function test_model_loading_callback_is_not_triggered_when_model_already_loaded(): void
    {
        $product = BuyableProduct::create(['name' => 'Test', 'price' => 1000]);
        $item = CartItem::fromBuyable($product);
        $item->setModel($product);

        $callbackTriggered = false;
        $item->setModelLoadingCallback(function () use (&$callbackTriggered) {
            $callbackTriggered = true;
        });

        // Model is already loaded, callback should not be triggered
        $item->model();

        $this->assertFalse($callbackTriggered);
    }

    public function test_collection_load_models_batch_loads_all_models(): void
    {
        // Create products
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);
        $product3 = BuyableProduct::create(['name' => 'Product 3', 'price' => 3000]);

        // fromBuyable() creates item with buyableType/buyableId but does NOT set model
        // This simulates items loaded from storage
        $item1 = CartItem::fromBuyable($product1);
        $item2 = CartItem::fromBuyable($product2);
        $item3 = CartItem::fromBuyable($product3);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
            $item3->rowId => $item3,
        ]);

        // Verify models not loaded yet (fromBuyable doesn't call setModel)
        $this->assertFalse($item1->hasModelLoaded());
        $this->assertFalse($item2->hasModelLoaded());
        $this->assertFalse($item3->hasModelLoaded());

        // Enable query log to count queries
        DB::enableQueryLog();
        DB::flushQueryLog();

        // Load models
        $collection->loadModels();

        // Should be 1 query for all products
        $this->assertCount(1, DB::getQueryLog());

        // All models should be loaded
        $this->assertTrue($item1->hasModelLoaded());
        $this->assertTrue($item2->hasModelLoaded());
        $this->assertTrue($item3->hasModelLoaded());

        // Models should be correct
        $this->assertSame('Product 1', $item1->model()->getBuyableDescription());
        $this->assertSame('Product 2', $item2->model()->getBuyableDescription());
        $this->assertSame('Product 3', $item3->model()->getBuyableDescription());
    }

    public function test_collection_load_models_groups_by_buyable_type(): void
    {
        // Create products and services
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);
        $service1 = BuyableService::create(['name' => 'Service 1', 'price' => 500]);

        // fromBuyable() uses getBuyableIdentifier() as id, and rowId is hash(id + options)
        // Since Product and Service both start at id=1, we need options to create unique rowIds
        $item1 = CartItem::fromBuyable($product1, options: ['type' => 'product']);
        $item2 = CartItem::fromBuyable($product2, options: ['type' => 'product']);
        $item3 = CartItem::fromBuyable($service1, options: ['type' => 'service']);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
            $item3->rowId => $item3,
        ]);

        // Verify all 3 items are in the collection (options make rowIds unique)
        $this->assertCount(3, $collection);

        // Enable query log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // Load models
        $collection->loadModels();

        // Should be 2 queries (one per buyable type)
        $this->assertCount(2, DB::getQueryLog());

        // All models should be loaded
        $this->assertTrue($item1->hasModelLoaded());
        $this->assertTrue($item2->hasModelLoaded());
        $this->assertTrue($item3->hasModelLoaded());
    }

    public function test_collection_load_models_skips_already_loaded_models(): void
    {
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);

        // Item 1 with model pre-loaded (simulates adding Buyable directly)
        $item1 = CartItem::fromBuyable($product1);
        $item1->setModel($product1);

        // Item 2 without model (simulates loading from storage)
        $item2 = CartItem::fromBuyable($product2);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $collection->loadModels();

        // Should only query for item2's model (item1 already loaded)
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);

        // Both should now have models loaded
        $this->assertTrue($item1->hasModelLoaded());
        $this->assertTrue($item2->hasModelLoaded());
    }

    public function test_collection_load_models_handles_empty_collection(): void
    {
        $collection = new CartItemCollection();

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Should not throw and should not query
        $collection->loadModels();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_collection_load_models_handles_missing_buyable_type(): void
    {
        $item = new CartItem(id: 1, quantity: 1, buyableType: null);
        $collection = new CartItemCollection([$item->rowId => $item]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Should not throw
        $collection->loadModels();

        // No queries should be made
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_collection_load_models_handles_nonexistent_class(): void
    {
        $item = new CartItem(
            id: 1,
            quantity: 1,
            buyableType: 'NonExistent\\Class\\Name'
        );
        $collection = new CartItemCollection([$item->rowId => $item]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Should not throw
        $collection->loadModels();

        // No queries should be made
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_cart_instance_sets_model_loading_callback_on_add(): void
    {
        $product = BuyableProduct::create(['name' => 'Test', 'price' => 1000]);

        Cart::fake();
        Cart::fakeResolver(1000);

        // When adding a Buyable directly, model is already set
        Cart::add($product);

        $items = Cart::content();
        $item = $items->first();

        // Model should already be loaded when adding Buyable directly
        $this->assertTrue($item->hasModelLoaded());
        $this->assertSame('Test', $item->model()->getBuyableDescription());
    }

    public function test_cart_instance_load_models_method(): void
    {
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);

        // fromBuyable() simulates items loaded from storage (no model set)
        $item1 = CartItem::fromBuyable($product1);
        $item2 = CartItem::fromBuyable($product2);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Load models via collection
        $collection->loadModels();

        // Should be 1 batch query
        $this->assertCount(1, DB::getQueryLog());

        // All items should have models loaded
        $this->assertTrue($item1->hasModelLoaded());
        $this->assertTrue($item2->hasModelLoaded());
    }

    public function test_first_model_access_triggers_batch_load_for_all_items(): void
    {
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);
        $product3 = BuyableProduct::create(['name' => 'Product 3', 'price' => 3000]);

        // fromBuyable() creates items without models
        $item1 = CartItem::fromBuyable($product1);
        $item2 = CartItem::fromBuyable($product2);
        $item3 = CartItem::fromBuyable($product3);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
            $item3->rowId => $item3,
        ]);

        // Set up callback that loads all models
        foreach ($collection as $item) {
            $item->setModelLoadingCallback(fn () => $collection->loadModels());
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Access only the first item's model
        $item1->model();

        // Should be 1 batch query for all items
        $this->assertCount(1, DB::getQueryLog());

        // All items should have models loaded (not just item1)
        $this->assertTrue($item1->hasModelLoaded());
        $this->assertTrue($item2->hasModelLoaded());
        $this->assertTrue($item3->hasModelLoaded());
    }

    public function test_subsequent_model_access_does_not_query(): void
    {
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);

        $item1 = CartItem::fromBuyable($product1);
        $item2 = CartItem::fromBuyable($product2);

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        foreach ($collection as $item) {
            $item->setModelLoadingCallback(fn () => $collection->loadModels());
        }

        // First access - triggers batch load
        $item1->model();

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Second access to any model - should not query
        $item1->model();
        $item2->model();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_model_fallback_to_individual_query_when_no_callback(): void
    {
        $product = BuyableProduct::create(['name' => 'Test', 'price' => 1000]);

        // fromBuyable() without callback - will fall back to individual query
        $item = CartItem::fromBuyable($product);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $model = $item->model();

        // Should fall back to individual query
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame($product->id, $model->getBuyableIdentifier());
    }

    public function test_cart_instance_wires_callback_on_content_load(): void
    {
        $product1 = BuyableProduct::create(['name' => 'Product 1', 'price' => 1000]);
        $product2 = BuyableProduct::create(['name' => 'Product 2', 'price' => 2000]);

        // fromBuyable() simulates items loaded from storage
        $item1 = CartItem::fromBuyable($product1);
        $item2 = CartItem::fromBuyable($product2);

        $content = new CartContent(
            items: new CartItemCollection([
                $item1->rowId => $item1,
                $item2->rowId => $item2,
            ])
        );

        // Create driver that returns our content
        $driver = new class($content) extends ArrayDriver {
            private CartContent $storedContent;

            public function __construct(CartContent $content)
            {
                $this->storedContent = $content;
            }

            public function get(string $instance, ?string $identifier = null): ?CartContent
            {
                return $this->storedContent;
            }
        };

        $resolver = $this->createFakeResolver(1000);
        $cart = new CartInstance('default', $driver, $resolver);
        $cart->setEventsEnabled(false);

        // Access content - this should wire up the callbacks
        $items = $cart->content();

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Access first model - should trigger batch load for all
        $items->first()->model();

        // Should be 1 batch query
        $this->assertCount(1, DB::getQueryLog());

        // All items should have models loaded
        foreach ($items as $item) {
            $this->assertTrue($item->hasModelLoaded());
        }
    }

    private function createFakeResolver(int $price): PriceResolver
    {
        return new class($price) implements PriceResolver {
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
}
