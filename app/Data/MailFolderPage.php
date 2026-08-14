<?php

namespace App\Data;

use Illuminate\Support\Collection;

class MailFolderPage
{
    /**
     * @param  Collection<int, MailMessageDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly string $folder,
        public readonly ?string $search = null,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->page <= 1;
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->lastPage();
    }
}
