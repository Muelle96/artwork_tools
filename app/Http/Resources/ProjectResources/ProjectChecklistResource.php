<?php

namespace App\Http\Resources\ProjectResources;

use App\Http\Resources\ChecklistIndexResource;
use App\Http\Resources\ContractResource;
use App\Http\Resources\CopyrightResource;
use App\Http\Resources\DepartmentIndexResource;
use App\Http\Resources\ProjectFileResource;
use App\Http\Resources\ProjectHeadlineResource;
use App\Http\Resources\UserResourceWithoutShifts;
use App\Models\Freelancer;
use App\Models\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Project
 */
class ProjectChecklistResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $historyArray = [];
        $historyComplete = $this->historyChanges()->all();

        foreach ($historyComplete as $history){
            $historyArray[] = [
                'changes' => json_decode($history->changes),
                'created_at' => $history->created_at->diffInHours() < 24
                    ? $history->created_at->diffForHumans()
                    : $history->created_at->format('d.m.Y, H:i'),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'isMemberOfADepartment' => $this->departments->contains(fn ($department) => $department->users->contains(Auth::user())),
            'key_visual_path' => $this->key_visual_path,

            'write_auth' => $this->writeUsers,
            'users' => UserResourceWithoutShifts::collection($this->users)->resolve(),

            'public_checklists' => ChecklistIndexResource::collection($this->checklists->whereNull('user_id'))->resolve(),
            'private_checklists' => ChecklistIndexResource::collection($this->checklists->where('user_id', Auth::id()))->resolve(),

            //needed for ProjectShowHeaderComponent
            'project_history' => $historyArray,
            'delete_permission_users' => $this->delete_permission_users,

            //needed for project Second Sidenav
            'entry_fee' => $this->entry_fee,
            'registration_required' => $this->registration_required,
            'register_by' => $this->register_by,
            'registration_deadline' => $this->registration_deadline,
            'closed_society' => $this->closed_society,
            'num_of_guests' => $this->num_of_guests,
            'project_managers' => $this->managerUsers,
            'departments' => DepartmentIndexResource::collection($this->departments)->resolve(),
        ];
    }
}
