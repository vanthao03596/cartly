<?php

declare(strict_types=1);

namespace Cart\Conditions;

use Cart\Contracts\Condition;

/**
 * Base class for all conditions.
 */
abstract class BaseCondition implements Condition
{
    /**
     * The unique name of the condition.
     */
    protected string $name;

    /**
     * The type: 'tax', 'discount', 'shipping', 'fee'.
     */
    protected string $type;

    /**
     * The target: 'subtotal', 'total', 'item'.
     */
    protected string $target = 'subtotal';

    /**
     * The order for applying conditions (lower = first).
     */
    protected int $order = 0;

    /**
     * Additional attributes for the condition.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @param  string  $name  Unique name for this condition
     * @param  array<string, mixed>  $attributes  Additional attributes
     */
    public function __construct(string $name, array $attributes = [])
    {
        $this->name = $name;
        $this->attributes = $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Get an attribute value.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set an attribute value.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get all attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'class' => static::class,
            'name' => $this->name,
            'type' => $this->type,
            'target' => $this->target,
            'order' => $this->order,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore-next-line new.static */
        $instance = new static(
            name: $data['name'],
            attributes: $data['attributes'] ?? [],
        );

        if (isset($data['target'])) {
            $instance->target = $data['target'];
        }

        if (isset($data['order'])) {
            $instance->order = $data['order'];
        }

        return $instance;
    }
}
