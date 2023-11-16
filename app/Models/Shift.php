<?php

namespace App\Models;

use Antonrom\ModelChangesHistory\Traits\HasChangesHistory;
use App\Casts\TimeWithoutSeconds;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use function Clue\StreamFilter\fun;


/**
 * App\Models\Shift
 * @property int $id
 * @property int $event_id
 * @property string $start
 * @property string $end
 * @property int $break_minutes
 * @property int $craft_id
 * @property int $number_employees
 * @property int $number_masters
 * @property string|null $description
 * @property bool $is_committed
 * @property string|null $shift_uuid
 * @property string|null $event_start_day
 * @property string|null $event_end_day
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \App\Models\Craft $craft
 * @property-read \App\Models\Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Freelancer[] $freelancer
 * @property-read int|null $freelancer_count
 * @property-read int $currentCount
 * @property-read int $empty_master_count
 * @property-read int $empty_user_count
 * @property-read int $maxCount
 * @property-read float $master_count
 * @property-read int $user_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Support\Collection $history
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ServiceProvider[] $service_provider
 * @property-read int|null $service_provider_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $masters
 * @property-read int|null $masters_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $employees
 * @property-read int|null $employees_count
 * @property-read array $allUsers
 * @property-read bool $infringement
 * @property-read string $break_formatted
 */
class Shift extends Model
{
    use HasFactory, HasChangesHistory;

    protected $fillable = [
        'event_id',
        'start',
        'end',
        'break_minutes',
        'craft_id',
        'number_employees',
        'number_masters',
        'description',
        'is_committed',
        'shift_uuid',
        'event_start_day',
        'event_end_day'
    ];

    protected $casts = [
        'start' => TimeWithoutSeconds::class,
        'end' => TimeWithoutSeconds::class,
        'is_committed' => 'boolean'
    ];

    protected $with = ['craft'];

    protected $appends = ['break_formatted', 'user_count', 'empty_user_count', 'empty_master_count', 'master_count', 'allUsers', 'currentCount', 'maxCount', 'infringement'];

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Event::class)->without(['series']);
    }

    public function craft(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Craft::class)->without(['users']);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_user', 'shift_id', 'user_id')
            ->withPivot(['is_master', 'shift_count'])
            ->orderByPivot('is_master', 'desc')
            ->withCasts(['is_master' => 'boolean'])
            ->without(['calender_settings']);
    }

    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Freelancer::class, 'shifts_freelancers', 'shift_id', 'freelancer_id')
            ->withPivot(['is_master', 'shift_count'])
            ->orderByPivot('is_master', 'desc')
            ->withCasts(['is_master' => 'boolean']);
    }

    public function service_provider(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ServiceProvider::class, 'shifts_service_providers', 'shift_id', 'service_provider_id')
            ->withPivot(['is_master', 'shift_count'])
            ->orderByPivot('is_master', 'desc')
            ->withCasts(['is_master' => 'boolean'])
            ->without(['contacts']);
    }

    public function getCurrentCountAttribute(): int
    {
        return $this->users->count() + $this->freelancer->count() + $this->service_provider->count();
    }

    public function getMaxCountAttribute(): int
    {
        return $this->number_employees + $this->number_masters;
    }

    public function getMasterCountAttribute(): float
    {
        return $this->getWorkerCount(true);
    }

    public function getEmptyMasterCountAttribute(): float
    {
        return $this->number_masters - $this->getWorkerCount(true);
    }

    public function getEmptyUserCountAttribute(): float
    {
        return $this->number_employees - $this->getWorkerCount();
    }

    protected function getWorkerCount($is_master = false): float
    {
        $relations = ['users' => function($query){
            $query->without(['calender_settings']);
        }, 'service_provider', 'freelancer'];

        $this->load($relations, [
            'pivot' => function ($query) use ($is_master) {
                $query->where('is_master', $is_master);
            }
        ]);

        $totalCount = 0;

        // Iterieren über jede Beziehung
        foreach ($relations as $relation) {
            foreach ($this->$relation as $entity) {
                $totalCount += 1 / $entity->pivot->shift_count;
            }
        }

        return $totalCount;
    }


    public function getUserCountAttribute(): float
    {
        return $this->getWorkerCount();
    }

    public function getHistoryAttribute(): \Illuminate\Support\Collection
    {
        return $this->historyChanges();
    }

    public function getMastersAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        // Eager Loading für alle Meister-Beziehungen mit 'is_master' true
        $relations = ['users' => function($query){
            $query->without(['calender_settings']);
        }, 'freelancer', 'service_provider'];

        $this->load($relations, [
            'pivot' => function ($query) {
                $query->where('is_master', true);
            }
        ]);

        $masterCollection = collect();

        // Fügen Sie alle Meister-Entitäten in eine einzige Collection ein
        foreach ($relations as $relation) {
            $masterCollection = $masterCollection->concat($this->$relation);
        }

        return $masterCollection;
    }


    public function getEmployeesAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        // Eager Loading für alle Mitarbeiter-Beziehungen mit 'is_master' false
        $relations = ['users' => function($query){
            $query->without(['calender_settings']);
        }, 'freelancer', 'service_provider'];

        $this->load($relations, [
            'pivot' => function ($query) {
                $query->where('is_master', false);
            }
        ]);

        $employeeCollection = collect();

        // Fügen Sie alle Mitarbeiter-Entitäten in eine einzige Collection ein
        foreach ($relations as $relation) {
            $employeeCollection = $employeeCollection->merge($this->$relation);
        }

        return $employeeCollection;
    }

    public function getBreakFormattedAttribute(): string
    {
        $hours = intdiv($this->break_minutes, 60) . ':' . ($this->break_minutes % 60);
        return Carbon::parse($hours)->format('H:i');
    }

    public function getInfringementAttribute(): bool
    {
        $start = Carbon::parse($this->start);
        $end = Carbon::parse($this->end);
        $diff = $start->diffInRealMinutes($end);
        $break = $this->break_minutes;

        if (($diff > 360 && $diff < 540 && $break < 30) || ($diff > 540 && $break < 45)) {
            return true;
        }
        return false;
    }


    public function getAllUsersAttribute(): array
    {
        return [
            'users' => $this->users,
            'freelancers' => $this->freelancer,
            'service_providers' => $this->service_provider,
        ];
    }

}
