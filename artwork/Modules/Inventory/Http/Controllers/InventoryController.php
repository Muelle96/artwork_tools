<?php

namespace Artwork\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FilterController;
use Artwork\Modules\Area\Services\AreaService;
use Artwork\Modules\Calendar\Services\CalendarDataService;
use Artwork\Modules\Calendar\Services\CalendarService;
use Artwork\Modules\Craft\Services\CraftService;
use Artwork\Modules\Event\Models\Event;
use Artwork\Modules\EventType\Services\EventTypeService;
use Artwork\Modules\Filter\Services\FilterService;
use Artwork\Modules\InventoryManagement\Models\CraftInventoryItem;
use Artwork\Modules\InventoryManagement\Services\CraftsInventoryColumnService;
use Artwork\Modules\InventoryManagement\Services\InventoryManagementUserFilterService;
use Artwork\Modules\InventoryScheduling\Http\Requests\DropItemOnInventoryRequest;
use Artwork\Modules\InventoryScheduling\Services\CraftInventoryItemEventService;
use Artwork\Modules\Room\Services\RoomService;
use Artwork\Modules\RoomAttribute\Services\RoomAttributeService;
use Artwork\Modules\RoomCategory\Services\RoomCategoryService;
use Artwork\Modules\User\Services\UserService;
use Illuminate\Auth\AuthManager;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ResponseFactory;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly CraftService $craftService,
        private readonly CraftsInventoryColumnService $craftsInventoryColumnService,
        private readonly InventoryManagementUserFilterService $inventoryManagementUserFilterService,
        private readonly CalendarDataService $calendarDataService,
        private readonly CraftInventoryItemEventService $craftInventoryItemEventService,
        private readonly ResponseFactory $responseFactory
    ) {
    }

    public function inventory(): Response
    {
        return $this->responseFactory->render(
            'Inventory/InventoryManagement/Inventory',
            [
                'columns' => $this->craftsInventoryColumnService->getAllOrdered(),
                'crafts' => $this->craftService->getAllWithInventoryCategoriesRelations(),
                'craftFilters' => $this->inventoryManagementUserFilterService
                    ->getFilterOfUser($this->authManager->id())
            ]
        );
    }

    /**
     * @throws Throwable
     */
    public function scheduling(
        UserService $userService,
    ): Response {
        [$startDate, $endDate] = $userService->getUserCalendarFilterDatesOrDefault($userService->getAuthUser());

        $showCalendar = $this->calendarDataService->createCalendarData(
            $startDate,
            $endDate,
            null,
            $userService->getAuthUser()->getAttribute('calendar_filter'),
            null,
            true
        );

        $crafts = $this->craftService->getCraftsWithInventory();

        return Inertia::render('Inventory/Scheduling', [
            'dateValue' => $showCalendar['dateValue'],
            'calendar' => $showCalendar['roomsWithEvents'],
            'days' => $showCalendar['days'],
            'crafts' => $crafts
        ]);
    }

    public function dropItemToEvent(
        DropItemOnInventoryRequest $request,
        CraftInventoryItem $item,
        Event $event
    ): void {
        $this->craftInventoryItemEventService->dropItemToEvent(
            $item,
            $event,
            $this->authManager->id(),
            $request->integer('quantity')
        );
    }
}
