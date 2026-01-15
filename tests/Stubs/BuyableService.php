<?php

declare(strict_types=1);

namespace Cart\Tests\Stubs;

use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\Traits\CanBeBought;
use Illuminate\Database\Eloquent\Model;

/**
 * Test stub for a buyable service model.
 *
 * @property int $id
 * @property string $name
 * @property int $price
 */
class BuyableService extends Model implements Buyable, Priceable
{
    use CanBeBought;

    protected $table = 'services';

    protected $guarded = [];

    public $timestamps = false;
}
