<?php

declare(strict_types=1);

namespace Cart;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

final class CartContext
{
    /**
     * @param Authenticatable|null $user The authenticated user
     * @param string $instance The cart instance name
     * @param string|null $currency Currency code (e.g., 'USD')
     * @param string|null $locale Locale code (e.g., 'en_US')
     * @param array<string, mixed> $meta Additional context metadata
     */
    public function __construct(
        public readonly ?Authenticatable $user = null,
        public readonly string $instance = 'default',
        public readonly ?string $currency = null,
        public readonly ?string $locale = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Create a context from the current application state.
     */
    public static function current(?string $instance = null): self
    {
        return new self(
            user: Auth::user(),
            instance: $instance ?? config('cart.default_instance', 'default'),
            currency: config('cart.format.currency_symbol', '$'),
            locale: App::getLocale(),
        );
    }

    /**
     * Create a new context with a different user.
     */
    public function withUser(?Authenticatable $user): self
    {
        return new self(
            user: $user,
            instance: $this->instance,
            currency: $this->currency,
            locale: $this->locale,
            meta: $this->meta,
        );
    }

    /**
     * Create a new context with a different instance.
     */
    public function withInstance(string $instance): self
    {
        return new self(
            user: $this->user,
            instance: $instance,
            currency: $this->currency,
            locale: $this->locale,
            meta: $this->meta,
        );
    }

    /**
     * Create a new context with additional meta.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return new self(
            user: $this->user,
            instance: $this->instance,
            currency: $this->currency,
            locale: $this->locale,
            meta: array_merge($this->meta, $meta),
        );
    }

    /**
     * Get a hash of the context for cache keying.
     */
    public function hash(): string
    {
        $userId = $this->user?->getAuthIdentifier() ?? 'guest';

        $data = [
            'user_id' => $userId,
            'currency' => $this->currency ?? 'USD',
            'locale' => $this->locale ?? 'en',
        ];

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Fallback to simple string concatenation if JSON encoding fails
            $json = "{$userId}:{$data['currency']}:{$data['locale']}";
        }

        return hash('xxh128', $json);
    }

    /**
     * Convert to array.
     *
     * @return array{user_id: mixed, instance: string, currency: string|null, locale: string|null, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user?->getAuthIdentifier(),
            'instance' => $this->instance,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'meta' => $this->meta,
        ];
    }
}
