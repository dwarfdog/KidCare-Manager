<?php

namespace App\Service;

final class ApplicationSettings
{
    public function __construct(
        private readonly string $currency,
        private readonly string $defaultCareStart,
        private readonly string $defaultCareEnd,
        private readonly int $defaultCareMeals,
    ) {
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function defaultCareStart(): string
    {
        return $this->defaultCareStart;
    }

    public function defaultCareEnd(): string
    {
        return $this->defaultCareEnd;
    }

    public function defaultCareMeals(): int
    {
        return max(0, $this->defaultCareMeals);
    }
}
