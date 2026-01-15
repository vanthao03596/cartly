<?php

declare(strict_types=1);

namespace Cart\Tests\Stubs;

use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\Traits\CanBeBought;
use Illuminate\Database\Eloquent\Model;

/**
 * Test stub for a buyable product model.
 *
 * @property int $id
 * @property string $name
 * @property int $price
 */
class BuyableProduct extends Model implements Buyable, Priceable
{
    use CanBeBought;

    protected $table = 'products';

    protected $guarded = [];

    public $timestamps = false;
}
