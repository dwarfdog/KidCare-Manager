<?php

namespace App\Model;

use App\Entity\BankCategory;

final class BankTransactionListCriteria
{
    public const DEFAULT_PER_PAGE = 25;

    public function __construct(
        public readonly int $page = 1,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        public readonly ?BankCategory $category = null,
        public readonly ?string $type = null,
        public readonly ?bool $verified = null,
        public readonly string $sort = 'date',
        public readonly string $direction = 'desc',
        public readonly int $perPage = self::DEFAULT_PER_PAGE,
    ) {
    }

    public function withPage(int $page): self
    {
        return new self(
            max(1, $page),
            $this->from,
            $this->to,
            $this->category,
            $this->type,
            $this->verified,
            $this->sort,
            $this->direction,
            $this->perPage,
        );
    }

    /** @return array<string, int|string> */
    public function toQuery(string $prefix, bool $includePage = true): array
    {
        $query = [];
        if ($includePage) {
            $query[$prefix . '_page'] = $this->page;
        }
        if ($this->from !== null) {
            $query[$prefix . '_from'] = $this->from->format('Y-m-d');
        }
        if ($this->to !== null) {
            $query[$prefix . '_to'] = $this->to->format('Y-m-d');
        }
        if ($this->category !== null) {
            $query[$prefix . '_category'] = $this->category->getPublicId();
        }
        if ($this->type !== null) {
            $query[$prefix . '_type'] = $this->type;
        }
        if ($this->verified !== null) {
            $query[$prefix . '_verified'] = $this->verified ? 'verified' : 'unverified';
        }
        if ($this->sort !== 'date') {
            $query[$prefix . '_sort'] = $this->sort;
        }
        $query[$prefix . '_direction'] = $this->direction;
        if ($this->perPage !== self::DEFAULT_PER_PAGE) { $query[$prefix . '_per_page'] = $this->perPage; }

        return $query;
    }
}
