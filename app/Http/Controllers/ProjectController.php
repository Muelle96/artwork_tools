<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Category;
use App\Models\Checklist;
use App\Models\ChecklistTemplate;
use App\Models\Department;
use App\Models\Genre;
use App\Models\Project;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(Project::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function index()
    {
        return inertia('Projects/ProjectManagement', [
            'projects' => Project::paginate(10)->through(fn($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'number_of_participants' => $project->number_of_participants,
                'cost_center' => $project->cost_center,
                'sector' => $project->sector,
                'category' => $project->category,
                'genre' => $project->genre,
                'users' => $project->users->map(fn($user) => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url
                ]),
                'departments' => $project->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                    'users' => $department->users->map(fn($user) => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_photo_url' => $user->profile_photo_url
                    ]),
                ])
            ]),
            'users' => User::all(),
            'categories' => Category::paginate(10)->through(fn($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'svg_name' => $category->svg_name,
                'projects' => $category->projects
            ]),
            'genres' => Genre::paginate(10)->through(fn($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
                'projects' => $genre->projects
            ]),
            'sectors' => Sector::paginate(10)->through(fn($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'projects' => $sector->projects
            ])
        ]);
    }

    public function search_departments_and_users(SearchRequest $request): array
    {

        $this->authorize('viewAny', Department::class);
        $this->authorize('viewAny', User::class);

        return [
            'departments' => Department::search($request->input('query'))->get(),
            'users' => User::search($request->input('query'))->get()
        ];

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function create()
    {
        return inertia('Projects/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function store(StoreProjectRequest $request)
    {
        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'number_of_participants' => $request->number_of_participants,
            'cost_center' => $request->cost_center,
            'sector_id' => $request->sector_id,
            'category_id' => $request->sector_id,
            'genre_id' => $request->genre_id,
        ]);

        if (Auth::user()->can('update users')) {
            $project->users()->sync(
                collect($request->assigned_user_ids)
                    ->map(function ($user) {
                        return $user;
                    })
            );
        } else {
            return response()->json(['error' => 'Not authorized to assign users to a project.'], 403);
        }

        $project->departments()->sync(
            collect($request->assigned_departments)
                ->map(function ($department) {

                    $this->authorize('update', Department::find($department['id']));

                    return $department['id'];
                })
        );

        $project->project_histories()->create([
            "user_id" => Auth::id(),
            "description" => "Projekt angelegt"
        ]);

        return Redirect::route('projects')->with('success', 'Project created.');
    }

    /**
     * Display the specified resource.
     *
     * @param Project $project
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function show(Project $project)
    {

        $public_checklists = Checklist::where('project_id', $project->id)->where('user_id', null)->get();
        $private_checklists = $project->checklists()->where('user_id', Auth::id())->get();

        $project_admins = User::whereHas('projects', function ($q) use ($project) {
            $q->where('is_admin', 1);
        })->get();
        $project_managers = User::whereHas('projects', function ($q) use ($project) {
            $q->where('is_manager', 1);
        })->get();


        return inertia('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'number_of_participants' => $project->number_of_participants,
                'cost_center' => $project->cost_center,
                'sector' => $project->sector,
                'category' => $project->category,
                'genre' => $project->genre,
                'project_admins' => $project_admins,
                'project_managers' => $project_managers,
                'users' => $project->users->map(fn($user) => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url
                ]),
                'project_files' => $project->project_files,
                'departments' => $project->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                    'users' => $department->users->map(fn($user) => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_photo_url' => $user->profile_photo_url
                    ]),
                ]),
                'public_checklists' => $public_checklists->map(fn($checklist) => [
                    'id' => $checklist->id,
                    'name' => $checklist->name,
                    'tasks' => $checklist->tasks->map(fn($task) => [
                        'id' => $task->id,
                        'name' => $task->name,
                        'description' => $task->description,
                        'deadline' =>  Carbon::parse($task->deadline)->format('d.m.Y, H:i'),
                        'deadline_dt_local' => Carbon::parse($task->deadline)->toDateTimeLocalString(),
                        'done' => $task->done,
                    ]),
                    'departments' => $checklist->departments->map(fn($department) => [
                        'id' => $department->id,
                        'name' => $department->name,
                        'svg_name' => $department->svg_name,
                    ])
                ]),
                'private_checklists' => $private_checklists->map(fn($checklist) => [
                    'id' => $checklist->id,
                    'name' => $checklist->name,
                    'tasks' => $checklist->tasks->map(fn($task) => [
                        'id' => $task->id,
                        'name' => $task->name,
                        'description' => $task->description,
                        'deadline' =>  Carbon::parse($task->deadline)->format('d.m.Y, H:i'),
                        'deadline_dt_local' => Carbon::parse($task->deadline)->toDateTimeLocalString(),
                        'done' => $task->done,
                    ])
                ]),
                'comments' => $project->comments->map(fn($comment) => [
                    'id' => $comment->id,
                    'text' => $comment->text,
                    'created_at' => $comment->created_at->format('d.m.Y, H:i'),
                    'user' => $comment->user
                ])
            ],
            'categories' => Category::paginate(10)->through(fn($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'svg_name' => $category->svg_name,
                'projects' => $category->projects
            ]),
            'genres' => Genre::paginate(10)->through(fn($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
                'projects' => $genre->projects
            ]),
            'sectors' => Sector::paginate(10)->through(fn($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'projects' => $sector->projects
            ]),
            'checklist_templates' => ChecklistTemplate::paginate(10)->through(fn($checklist_template) => [
                'id' => $checklist_template->id,
                'name' => $checklist_template->name,
                'task_templates' => $checklist_template->task_templates->map(fn($task_template) => [
                    'id' => $task_template->id,
                    'name' => $task_template->name,
                    'description' => $task_template->description
                ]),
            ])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Project $project
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function edit(Project $project)
    {
        return inertia('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'number_of_participants' => $project->number_of_participants,
                'cost_center' => $project->cost_center,
                'sector_id' => $project->sector_id,
                'category_id' => $project->sector_id,
                'genre_id' => $project->genre_id,
                'users' => $project->users->map(fn($user) => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url
                ]),
                'departments' => $project->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                    'users' => $department->users->map(fn($user) => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_photo_url' => $user->profile_photo_url
                    ]),
                ]),
            ],
            'users' => User::all(),
            'departments' => Department::all()
        ]);
    }

    private function history_description($change) {

        return match ($change) {
            'name' => 'Projektname wurde geändert',
            'description' => 'Kurzbeschreibung wurde geändert',
            'number_of_participants' => 'Anzahl Teilnehmer:innen geändert',
            'cost_center' => 'Kostenträger geändert',
            'sector_id'=> 'Bereich geändert',
            'category_id'=> 'Kategorie geändert',
            'genre_id'=> 'Genre geändert',
        };

    }

    private function add_to_history($project) {

        $changes = $project->getChanges();

        $changed_fields = array_keys($changes);

        dd($changed_fields);

        foreach ($changes as $change) {
            $project->project_histories()->create([
                "user_id" => Auth::id(),
                "description" => $this->history_description($change)
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProjectRequest $request
     * @param Project $project
     * @return JsonResponse|RedirectResponse
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {

        $update_properties = $request->only('name', 'description', 'number_of_participants', 'cost_center', 'sector_id', 'category_id', 'genre_id');

        $project->update($update_properties);

        $this->add_to_history($project);

        if (Auth::user()->can('update users')) {
            $project->users()->sync(
                collect($request->assigned_user_ids)
                    ->map(function ($user_id) {
                        return $user_id;
                    })
            );
        } else {
            return response()->json(['error' => 'Not authorized to assign users to a project.'], 403);
        }

        if (Auth::user()->can('update departments')) {
            $project->departments()->sync(
                collect($request->assigned_departments)
                    ->map(function ($department) {
                        return $department['id'];
                    })
            );
        } else {
            return response()->json(['error' => 'Not authorized to assign departments to a project.'], 403);
        }

        return Redirect::route('projects.update', $project->id)->with('success', 'Project updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Project $project
     * @return RedirectResponse
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return Redirect::back()->with('success', 'Project deleted');
    }
}
