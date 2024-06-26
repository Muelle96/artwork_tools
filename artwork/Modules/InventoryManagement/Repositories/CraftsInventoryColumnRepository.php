<?php

namespace Artwork\Modules\InventoryManagement\Repositories;

use Artwork\Core\Database\Repository\BaseRepository;
use Artwork\Modules\InventoryManagement\Models\CraftsInventoryColumn;
use Illuminate\Database\Eloquent\Collection;

readonly class CraftsInventoryColumnRepository extends BaseRepository
{
    public function getAllOrdered($orderBy = 'id', $orderByDirection = 'asc'): Collection
    {
        return CraftsInventoryColumn::query()
            ->orderBy($orderBy, $orderByDirection)
            ->get();
    }

    public function find(int $id): CraftsInventoryColumn
    {
        /** @var CraftsInventoryColumn $column */
        $column = CraftsInventoryColumn::find($id);

        return $column;
    }

    public function getAllItemCells(CraftsInventoryColumn $craftsInventoryColumn): Collection
    {
        return $craftsInventoryColumn->itemCells()->get();
    }
}
