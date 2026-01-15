<?php

declare(strict_types=1);

namespace Cart\Support;

use Cart\Contracts\Condition;
use Illuminate\Support\Collection;

class CalculationPipeline
{
    /**
     * The conditions to apply.
     *
     * @var Collection<string, Condition>
     */
    protected Collection $conditions;

    /**
     * The calculation steps log.
     *
     * @var array<int, array{name: string, type: string, order: int, before: int, after: int, change: int}>
     */
    protected array $steps = [];

    public function __construct()
    {
        $this->conditions = collect();
    }

    /**
     * Create a new pipeline instance.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set the conditions to apply.
     *
     * @param Collection<string, Condition> $conditions
     */
    public function through(Collection $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Process the value through all conditions.
     *
     * Conditions are sorted by order (lower = first) and applied sequentially.
     * The result is guaranteed to be non-negative (minimum 0).
     *
     * @param int $valueCents The starting value in cents
     * @return int The final value after all conditions applied, in cents
     */
    public function process(int $valueCents): int
    {
        $this->steps = [];

        if ($this->conditions->isEmpty()) {
            return $valueCents;
        }

        // Sort conditions by order (lower = first)
        $sorted = $this->conditions->sortBy(fn (Condition $c) => $c->getOrder());

        $current = $valueCents;

        foreach ($sorted as $condition) {
            $before = $current;
            $current = $condition->calculate($current);

            $this->steps[] = [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'order' => $condition->getOrder(),
                'before' => $before,
                'after' => $current,
                'change' => $current - $before,
            ];
        }

        return max(0, $current);
    }

    /**
     * Get the calculation steps (for debugging/display).
     *
     * Each step contains:
     * - name: The condition name
     * - type: The condition type (tax, discount, shipping, fee)
     * - order: The condition's sort order
     * - before: Value before this condition (in cents)
     * - after: Value after this condition (in cents)
     * - change: The difference (after - before, in cents)
     *
     * @return array<int, array{name: string, type: string, order: int, before: int, after: int, change: int}>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get breakdown by condition type.
     *
     * Returns an associative array where keys are condition types
     * and values are the total change for that type (in cents).
     *
     * Example: ['discount' => -1500, 'tax' => 850, 'shipping' => 599]
     *
     * @return array<string, int>
     */
    public function getBreakdown(): array
    {
        $breakdown = [];

        foreach ($this->steps as $step) {
            $type = $step['type'];
            $breakdown[$type] = ($breakdown[$type] ?? 0) + $step['change'];
        }

        return $breakdown;
    }

    /**
     * Get total change from all conditions.
     *
     * @return int The sum of all condition changes in cents
     */
    public function getTotalChange(): int
    {
        return array_sum(array_column($this->steps, 'change'));
    }

    /**
     * Check if the pipeline has been processed.
     */
    public function hasProcessed(): bool
    {
        return !empty($this->steps);
    }

    /**
     * Get the number of conditions that were applied.
     */
    public function getStepCount(): int
    {
        return count($this->steps);
    }
}
