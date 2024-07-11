<?php

namespace Artwork\Modules\Checklist\Services;

use Artwork\Core\Database\Models\Model;
use Artwork\Modules\Checklist\Http\Requests\ChecklistUpdateRequest;
use Artwork\Modules\Checklist\Http\Resources\ChecklistIndexResource;
use Artwork\Modules\Checklist\Models\Checklist;
use Artwork\Modules\Checklist\Repositories\ChecklistRepository;
use Artwork\Modules\ChecklistTemplate\Http\Resources\ChecklistTemplateIndexResource;
use Artwork\Modules\ChecklistTemplate\Models\ChecklistTemplate;
use Artwork\Modules\Project\Models\Project;
use Artwork\Modules\ProjectTab\Models\ComponentInTab;
use Artwork\Modules\Task\Models\Task;
use Artwork\Modules\Task\Services\TaskService;
use Artwork\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use stdClass;

readonly class ChecklistService
{
    public function __construct(private ChecklistRepository $checklistRepository)
    {
    }

    public function updateByRequest(
        Checklist $checklist,
        ChecklistUpdateRequest $request,
        TaskService $taskService
    ): Checklist|Model {
        $checklist->fill($request->data());

        if ($request->get('tasks')) {
            $taskService->deleteByChecklist($checklist);
            $checklist->tasks()->delete();
            $checklist->tasks()->createMany($request->tasks);
        }

        return $this->checklistRepository->save($checklist);
    }

    public function getChecklistsWithMyTask(int $userId, int $filter): Collection
    {
        // get all Checklists where has tasks with task_users with user_id = $userId.
        // if there is a task with user_id = $userId, then the checklist is returned
        // but return only tasks with user_id = $userId
        // return only tasks where are not done an if in checklist all tasks are done, then return the checklist not
        $doneTask = false;
        if ($filter === 3) {
            $doneTask = true;
        }

        return Checklist::query()
            ->whereHas('tasks', function ($q) use ($userId): void {
                $q->whereHas('task_users', function ($q) use ($userId): void {
                    $q->where('user_id', $userId);
                });
            })
            ->with(['tasks' => function ($q) use ($filter, $doneTask, $userId): void {
                $q->where('done', $doneTask)
                    ->when($filter === 1, function ($q): void {
                        $q->orderBy('order');
                    })
                    ->when($filter === 2, function ($q): void {
                        $q->orderBy('deadline');
                    })
                    ->whereHas('task_users', function ($q) use ($userId): void {
                        $q->where('user_id', $userId);
                    });
            }])
            ->get();
    }

    public function getPrivateChecklists(int $userId, int $filter): Collection
    {
        $doneTask = false;
        if ($filter === 3) {
            $doneTask = true;
        }
        return Checklist::query()
            ->where('user_id', $userId)
            ->with(['tasks' => function ($q) use ($doneTask, $filter): void {
                $q->where('done', $doneTask)
                    ->when($filter === 1, function ($q): void {
                        $q->orderBy('order');
                    })
                    ->when($filter === 2, function ($q): void {
                        $q->orderBy('deadline');
                    });
            }])
            ->get();
    }

    public function assignUsersById(Checklist $checklist, TaskService $taskService, array $ids): void
    {
        $checklist->users()->sync($ids);
        $taskService->getByChecklist($checklist)->each(function (Task $task) use ($ids, $taskService): void {
            $taskService->syncTaskUsersWithDetach($task, $ids);
        });
    }

    public function delete(Checklist $checklist, TaskService $taskService): void
    {
        $taskService->deleteByChecklist($checklist);
        $this->checklistRepository->delete($checklist);
    }

    public function deleteAll(Collection|array $checklists, TaskService $taskService): void
    {
        /** @var Checklist $checklist */
        foreach ($checklists as $checklist) {
            $taskService->deleteAll($checklist->tasks);
            $this->checklistRepository->delete($checklist);
        }
    }

    public function restoreAll(Collection|array $checklists, TaskService $taskService): void
    {
        /** @var Checklist $checklist */
        foreach ($checklists as $checklist) {
            $checklist->restore();
            $taskService->restoreAll($checklist->tasks);
        }
    }

    public function forceDeleteAll(Collection|array $checklists, TaskService $taskService): void
    {
        /** @var Checklist $checklist */
        foreach ($checklists as $checklist) {
            $taskService->forceDeleteAll($checklist->tasks);
            $this->checklistRepository->forceDelete($checklist);
        }
    }

    public function restore(Checklist $checklist, TaskService $taskService): void
    {
        $checklist->restore();
        $taskService->restoreAll($checklist->tasks);
    }

    public function getProjectChecklists(
        Project $project,
        stdClass $headerObject,
        ComponentInTab $componentInTab
    ): stdClass {
        $headerObject->project->opened_checklists = User::where('id', Auth::id())
            ->first()->opened_checklists;
        $headerObject->project->checklist_templates = ChecklistTemplateIndexResource::collection(
            ChecklistTemplate::all()
        )->resolve();
        $headerObject->project->public_checklists = ChecklistIndexResource::collection(
            $project->checklists->whereNull('user_id')->whereIn('tab_id', $componentInTab->scope)
        )
            ->resolve();
        $headerObject->project->private_checklists = ChecklistIndexResource::collection(
            $project->checklists->where('user_id', Auth::id())->whereIn('tab_id', $componentInTab->scope)
        )->resolve();
        return $headerObject;
    }

    public function getProjectChecklistsAll(Project $project, stdClass $headerObject): stdClass
    {
        $headerObject->project->opened_checklists = User::where('id', Auth::id())
            ->first()->opened_checklists;
        $headerObject->project->checklist_templates = ChecklistTemplateIndexResource::collection(
            ChecklistTemplate::all()
        )->resolve();
        $headerObject->project->public_all_checklists = ChecklistIndexResource::collection(
            $project->checklists->whereNull('user_id')
        )
            ->resolve();
        $headerObject->project->private_all_checklists = ChecklistIndexResource::collection(
            $project->checklists->where('user_id', Auth::id())
        )->resolve();
        return $headerObject;
    }

    public function createNewChecklist(array $attributes): Checklist
    {
        return new Checklist($attributes);
    }

    public function duplicate(
        Checklist $checklist
    ): Checklist {
        return $this->createNewChecklist([
            'name' => $checklist->name . ' (copy)',
            'project_id' => $checklist->project_id,
            'user_id' => $checklist->user_id,
            'tab_id' => $checklist->tab_id
        ]);
    }

    public function getById(int $id): Checklist|null
    {
        return $this->checklistRepository->getById($id);
    }
}
