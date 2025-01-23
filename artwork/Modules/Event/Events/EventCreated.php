<?php

namespace Artwork\Modules\Event\Events;

use App\Http\Resources\MinimalShiftPlanShiftResource;
use Artwork\Modules\Event\Models\Event;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $event;
    public $roomId;

    public function __construct(Event $event, int $roomId)
    {
        $this->event = $event;
        $this->roomId = $roomId;
    }

    public function broadcastAs()
    {
        return 'event.created';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('event.room.' . $this->roomId);
    }

    public function broadcastWith(): array
    {
        $event = $this->event;
        $eventType = $event->event_type;
        $creator = $event->creator;
        $startTime = Carbon::parse($event->start_time);
        return [
            'event' => [
                'id' => $event->id,
                'start' => $startTime,
                'startTime' => Carbon::parse($event->start_time, 'Europe/Berlin')->format('Y-m-d H:i:s'),
                'end' => Carbon::parse($event->end_time, 'Europe/Berlin')->format('Y-m-d H:i:s'),
                'eventName' => $event->eventName,
                'description' => $event->description,
                'audience' => $event->audience,
                'isLoud' => $event->is_loud,
                'projectId' => $event->project_id,
                'projectName' => $event->project?->name,
                'eventTypeId' => $event->event_type_id,
                'eventStatusId' => $event->event_status_id,
                'eventStatusColor' => $event->eventStatus?->color,
                'eventTypeName' => $eventType?->name,
                'eventTypeAbbreviation' => $eventType?->abbreviation,
                'eventTypeColor' => $eventType?->hex_code,
                'created_at' => $event->created_at?->format('d.m.Y, H:i'),
                'occupancy_option' => $event->occupancy_option,
                'allDay' => $event->allDay,
                'eventTypeColorBackground' => $eventType->getAttribute('hex_code') . '33',
                'event_type_color' => $eventType->getAttribute('hex_code'),
                'shifts' => MinimalShiftPlanShiftResource::collection($event->shifts)->resolve(),
                'days_of_event' => $event->days_of_event,
                'days_of_shifts' => $event->getDaysOfShifts($event->shifts),
                'option_string' => $event->option_string,
                'formatted_dates' => $event->formatted_dates,
                'timesWithoutDates' => $event->timesWithoutDates,
                'is_series' => $event->is_series,
                'start_hour' => $event->getAttribute('start_hour') . ':00',
                'event_length_in_hours' => $event->getAttribute('event_length_in_hours'),
                'hours_to_next_day' => $event->getAttribute('hours_to_next_day'),
                'minutes_form_start_hour_to_start' => $event->getAttribute('minutes_form_start_hour_to_start'),
                'roomId' => $event->getAttribute('room_id'),
                'roomName' => $event->getAttribute('room')?->getAttribute('name'),
                'created_by' => [
                    'id' => $creator->getAttribute('id'),
                    'profile_photo_url' => $creator->getAttribute('profile_photo_url'),
                    'first_name' => $creator->getAttribute('first_name'),
                    'last_name' => $creator->getAttribute('last_name')
                ],
            ],
        ];
    }
}