<?php

namespace App\Models;

use Artwork\Modules\Room\Models\Room;
use Artwork\Modules\Room\Models\RoomAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoomRoomAttributeMapping extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'room_attribute_id'
    ];

    protected $table = 'room_room_attribute';

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomAttribute(): BelongsTo
    {
        return $this->belongsTo(RoomAttribute::class);
    }
}
