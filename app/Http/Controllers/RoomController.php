<?php

namespace App\Http\Controllers;

use Antonrom\ModelChangesHistory\Models\Change;
use App\Enums\NotificationConstEnum;
use App\Http\Resources\AdjoiningRoomIndexResource;
use App\Http\Resources\AttributeIndexResource;
use App\Http\Resources\CategoryIndexResource;
use App\Http\Resources\EventTypeResource;
use App\Http\Resources\ProjectIndexAdminResource;
use App\Http\Resources\RoomCalendarResource;
use App\Http\Resources\RoomIndexWithoutEventsResource;
use App\Models\Area;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Project;
use App\Models\Room;
use App\Models\RoomAttribute;
use App\Models\RoomCategory;
use App\Models\User;
use App\Support\Services\CollisionService;
use App\Support\Services\RoomService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

use function Clue\StreamFilter\fun;

use JetBrains\PhpStorm\NoReturn;

class RoomController extends Controller
{
    // init notification system
    public function __construct(protected RoomService $roomService)
    {
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getAllDayFree(Request $request): array
    {

        $period = CarbonPeriod::create(Carbon::parse($request->get('start'))->addHours(2), Carbon::parse($request->get('end')));

        // Convert the period to an array of dates
        $dates = $period->toArray();
        $roomIds = [];

        foreach ($dates as $date) {
            Debugbar::info($date);
            $events = Event::query()->occursAt(Carbon::parse($date))->get();
            foreach ($events as $event) {
                $roomIds[] = $event->room_id;
            }
        }
        $room_occurrences = array_count_values($roomIds);

        $rooms_with_events_everyDay = [];
        foreach ($room_occurrences as $key => $occurrence) {
            if ($occurrence == count($dates)) {
                $rooms_with_events_everyDay[] = $key;
            }
        }

        $rooms = Room::with('adjoining_rooms', 'main_rooms')->whereNotIn('id', $rooms_with_events_everyDay)->get()->map(fn(Room $room) => [
            'id' => $room->id,
            'name' => $room->name,
            'area' => $room->area,
            'label' => $room->name,
            'adjoining_rooms' => $room->adjoining_rooms->map(fn(Room $adjoining_room) => [
                'id' => $adjoining_room->id,
                'label' => $adjoining_room->name
            ]),
            'main_rooms' => $room->main_rooms->map(fn(Room $main_room) => [
                'id' => $main_room->id,
                'label' => $main_room->name
            ]),
            'categories' => $room->categories,
            'attributes' => $room->attributes
        ]);

        return [
            'rooms' => $rooms
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $room = Room::create([
            'name' => $request->name,
            'description' => $request->description,
            'temporary' => $request->temporary,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'area_id' => $request->area_id,
            'user_id' => $request->user_id,
            'everyone_can_book' => $request->everyone_can_book,
            'order' => Room::max('order') + 1,
        ]);

        if (!is_null($request->adjoining_rooms) && !is_null($request->room_attributes) && !is_null($request->room_categories)) {
            $room->adjoining_rooms()->sync($request->adjoining_rooms);
            $room->attributes()->sync($request->room_attributes);
            $room->categories()->sync($request->room_categories);
        }

        return Redirect::route('areas.management')->with('success', 'Room created.');
    }

    /**
     * Display the specified resource.
     *
     * @param Room $room
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function show(Room $room, Request $request, CalendarController $calendarController): \Inertia\Response|\Inertia\ResponseFactory
    {
        $room->load('creator');
        $projects = Project::query()->with(['access_budget', 'managerUsers'])->get();

        $showCalendar = $calendarController->createCalendarData('', null, $room);
        //dd($showCalendar['dateValue']);
        //dd($showCalendar['user_filters']);
        return inertia('Rooms/Show', [
            'room' => new RoomCalendarResource($room),
            'rooms' => RoomIndexWithoutEventsResource::collection(Room::all())->resolve(),
            'is_room_admin' => $room->users()->wherePivot('is_admin', true)->get()->contains(Auth::id()),
            'event_types' => EventTypeResource::collection(EventType::all())->resolve(),
            'projects' => ProjectIndexAdminResource::collection($projects)->resolve(),

            'available_categories' => RoomCategory::all(),
            'roomCategoryIds' => $room->categories()->pluck('room_category_id'),
            'roomCategories' => CategoryIndexResource::collection($room->categories),

            'available_attributes' => RoomAttribute::all(),
            'roomAttributeIds' => $room->attributes()->pluck('room_attribute_id'),
            'roomAttributes' => AttributeIndexResource::collection($room->attributes),

            'available_rooms' => Room::where('id', '!=', $room->id)->get(),
            'adjoiningRoomIds' => $room->adjoining_rooms()->pluck('adjoining_room_id'),
            'adjoiningRooms' => AdjoiningRoomIndexResource::collection($room->adjoining_rooms),
            'calendar' => $showCalendar['roomsWithEvents'],
            'days' => $showCalendar['days'],
            'eventsWithoutRoom' => $showCalendar['eventsWithoutRoom'],
            'filterOptions' => $showCalendar['filterOptions'],
            'dateValue' => $showCalendar['dateValue'],
            'personalFilters' => $showCalendar['personalFilters'],
            'calendarType' => $showCalendar['calendarType'],
            'selectedDate' => $showCalendar['selectedDate'],
            'user_filters' => $showCalendar['user_filters'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Room $room
     * @return RedirectResponse
     */
    public function update(Request $request, Room $room): RedirectResponse
    {

        $oldRoomDescription = $room->description;
        $oldRoomTitle = $room->name;
        $roomAdminsBefore = $room->users()->wherePivot('is_admin', true)->get();
        $oldAdjoiningRooms = $room->adjoining_rooms()->get();
        $oldRoomAttributes = $room->attributes()->get();
        $oldRoomCategories = $room->categories()->get();
        $oldTemporary = $room->temporary;
        $oldStartDate = $room->start_date;
        $oldEndDate = $room->end_date;

        $room->update($request->only('name', 'description', 'temporary', 'start_date', 'end_date', 'everyone_can_book'));

        if (!is_null($request->room_admins) && !is_null($request->requestable_by)) {
            $room_admins_ids = [];
            foreach ($request->room_admins as $room_admin) {
                $room_admins_ids[$room_admin['id']] = ['is_admin' => true];
            }

            $requestable_by_ids = [];
            foreach ($request->requestable_by as $can_request) {
                $requestable_by_ids[$can_request['id']] = ['can_request' => true];
            }

            $new_users = collect($room_admins_ids + $requestable_by_ids);
            $room->users()->detach();
            $room->users()->sync($new_users);
        }

        if (!is_null($request->adjoining_rooms) && !is_null($request->room_attributes) && !is_null($request->room_categories)) {
            $room->adjoining_rooms()->sync($request->adjoining_rooms);
            $room->attributes()->sync($request->room_attributes);
            $room->categories()->sync($request->room_categories);
        }

        $newRoomDescription = $room->description;
        $newRoomTitle = $room->name;
        $newAdjoiningRooms = $room->adjoining_rooms()->get();
        $newRoomAttributes = $room->attributes()->get();
        $roomAdminsAfter = $room->users()->wherePivot('is_admin', true)->get();
        $newRoomCategories = $room->categories()->get();
        $newRoomTemporary = $room->temporary;
        $newStartDate = $room->start_date;
        $newEndDate = $room->end_date;

        $this->roomService->checkAdjoiningRoomChanges($room->id, $oldAdjoiningRooms, $newAdjoiningRooms);
        $this->roomService->checkDescriptionChanges($room->id, $oldRoomDescription, $newRoomDescription);
        $this->roomService->checkMemberChanges($room, $roomAdminsBefore, $roomAdminsAfter);
        $this->roomService->checkTitleChanges($room->id, $oldRoomTitle, $newRoomTitle);
        $this->roomService->checkAttributeChanges($room->id, $oldRoomAttributes, $newRoomAttributes);
        $this->roomService->checkCategoryChanges($room->id, $oldRoomCategories, $newRoomCategories);
        $this->roomService->checkTemporaryChanges($room->id, $oldTemporary, $newRoomTemporary, $oldStartDate, $newStartDate, $oldEndDate, $newEndDate);


        $scheduling = new SchedulingController();
        $roomId = $room->id;
        foreach ($room->users()->wherePivot('is_admin', true)->get() as $user) {
            $scheduling->create($user->id, 'ROOM_CHANGES', 'ROOMS', $roomId);
        }

        return Redirect::back()->with('success', 'Room updated');
    }

    /**
     * Duplicates the room whose id is passed in the request
     */
    public function duplicate(Room $room): RedirectResponse
    {
        Room::create([
            'name' => '(Kopie) ' . $room->name,
            'description' => $room->description,
            'temporary' => $room->temporary,
            'start_date' => $room->start_date,
            'end_date' => $room->end_date,
            'area_id' => $room->area_id,
            'user_id' => Auth::id(),
            'order' => Room::max('order') + 1,
        ]);

        return Redirect::route('areas.management')->with('success', 'Room created.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Room $room
     * @return RedirectResponse
     */
    public function updateOrder(Request $request): RedirectResponse
    {
        foreach ($request->rooms as $room) {
            Room::findOrFail($room['id'])->update(['order' => $room['order']]);
        }

        return Redirect::back();
    }

    /**
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function getTrashed(): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Trash/Rooms', [
            'trashed_rooms' => Area::all()->map(fn($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'rooms' => RoomIndexWithoutEventsResource::collection($area->trashed_rooms)->resolve(),
            ])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Room $room
     * @return RedirectResponse
     */
    public function destroy(Room $room): RedirectResponse
    {

        $room->delete();

        return Redirect::route('areas.management')->with('success', 'Room moved to trash');
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $room = Room::onlyTrashed()->findOrFail($id);
        $room->forceDelete();

        return Redirect::route('rooms.trashed')->with('success', 'Room restored');
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    public function restore(int $id): RedirectResponse
    {
        $room = Room::onlyTrashed()->findOrFail($id);
        $room->restore();

        return Redirect::route('rooms.trashed')->with('success', 'Room restored');
    }


    public function collisionsCount(Request $request): array
    {
        $startDate = Carbon::parse($request['params']['start'])->setTimezone(config('app.timezone'));
        $endDate = Carbon::parse($request['params']['end'])->setTimezone(config('app.timezone'));

        $rooms = Room::all();
        $collisions = [];
        foreach ($rooms as $room) {
            $collisions[$room->id] = [
                Event::query()
                    ->whereOccursBetween($startDate, $endDate, true)
                    ->where('room_id', $room->id)->count()
            ];
        }


        return $collisions;
    }
}
