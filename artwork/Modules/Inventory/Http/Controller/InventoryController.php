<?php

namespace Artwork\Modules\Inventory\Http\Controller;

use App\Http\Controllers\Controller;
use Artwork\Modules\Craft\Services\CraftService;
use Artwork\Modules\InventoryManagement\Services\CraftsInventoryColumnService;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly CraftService $craftService,
        private readonly CraftsInventoryColumnService $craftsInventoryColumnService
    ) {
    }

    public function inventory(): Response
    {
        return Inertia::render(
            'Inventory/Inventory',
            [
                'columns' => $this->craftsInventoryColumnService->getAllOrdered(),
                'crafts' => $this->craftService->getAllWithInventoryCategoriesRelations()
            ]
        );
    }

    public function scheduling(): Response
    {
        return Inertia::render(
            'Inventory/Scheduling',
            [
                'crafts' => $this->craftService->getAll()
            ]
        );
    }
}
