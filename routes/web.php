<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistTemplateController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractModuleController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\GlobalNotificationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\RoomAttributeController;
use App\Http\Controllers\RoomCategoryController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomFileController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [AppController::class, 'index']);

Route::get('/password_feedback', [AppController::class, 'getPasswordScore']);

Route::get('/setup', [AppController::class, 'showSetupPage'])->name('setup');
Route::post('/setup', [AppController::class, 'createAdmin'])->name('setup.create');

Route::get('/users/invitations/accept', [InvitationController::class, 'accept']);
Route::post('/users/invitations/accept', [InvitationController::class, 'createUser'])->name('invitation.accept');

Route::group(['middleware' => ['auth:sanctum', 'verified']], function() {

    //Hints
    Route::post('/toggle/hints', [AppController::class, 'toggle_hints'])->name('toggle.hints');

    Route::get('/dashboard', [EventController::class, 'showDashboard'])->name('dashboard');
    Route::get('/checklist/templates', function () { return Inertia::render('ChecklistTemplates/Edit'); })->name('checklistTemplates.edit');
    Route::get('/tool/settings', function () { return Inertia::render('Settings/ToolSettings'); })->name('tool.settings');
    Route::put('/tool/settings', [AppController::class, 'updateTool'])->name('tool.update');
    Route::put('/tool/settings/email', [AppController::class, 'updateEmailSettings'])->name('tool.updateMail');

    //Invitations
    Route::get('/users/invitations', [InvitationController::class, 'index'])->name('user.invitations');
    Route::get('/users/invitations/invite', [InvitationController::class, 'invite'])->name('user.invite');
    Route::get('/users/invitations/{invitation}/edit', [InvitationController::class, 'edit'])->name('user.invitations.edit');
    Route::post('/users/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::patch('/users/invitations/{invitation}', [InvitationController::class, 'update']);
    Route::delete('/users/invitations/{invitation}', [InvitationController::class, 'destroy']);

    //Users
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('user.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('user.update');
    Route::patch('/users/{user}/checklists', [UserController::class, 'update_checklist_status'])->name('user.checklists.update');
    Route::patch('/users/{user}/areas', [UserController::class, 'update_area_status'])->name('user.areas.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::post('/users/reset-password', [UserController::class, 'reset_user_password'])->name('user.reset.password');

    //Departments
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments');
    Route::get('/departments/search', [DepartmentController::class, 'search'])->name('departments.search');
    Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.profile');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.edit');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

    //Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects');
    Route::get('/projects/search', [ProjectController::class, 'search'])->name('projects.search');
    Route::get('/projects/trashed', [ProjectController::class, 'getTrashed'])->name('projects.trashed');
    Route::get('/projects/users_departments/search', [ProjectController::class, 'search_departments_and_users'])->name('users_departments.search');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::delete('/projects/{id}/force', [ProjectController::class, 'forceDelete'])->name('projects.force');
    Route::patch('/projects/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    //ProjectFiles
    Route::post('/projects/{project}/files', [ProjectFileController::class, 'store']);
    Route::get('/project_files/{project_file}', [ProjectFileController::class, 'download'])->name('download_file');;
    Route::delete('/project_files/{project_file}', [ProjectFileController::class, 'destroy']);
    Route::delete('/project_files/{id}/force_delete', [ProjectFileController::class, 'force_delete']);

    //Checklists
    Route::get('/checklists/create', [ChecklistController::class, 'create'])->name('checklists.create');
    Route::post('/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::get('/checklists/{checklist}', [ChecklistController::class, 'show']);
    Route::get('/checklists/{checklist}/edit', [ChecklistController::class, 'edit']);
    Route::patch('/checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
    Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy']);

    //ChecklistTemplates
    Route::get('/checklist_templates', [ChecklistTemplateController::class, 'index'])->name('checklist_templates.management');
    Route::get('/checklist_templates/create', [ChecklistTemplateController::class, 'create'])->name('checklist_templates.create');
    Route::get('/checklist_templates/search', [ChecklistTemplateController::class, 'search'])->name('checklist_templates.search');
    Route::post('/checklist_templates', [ChecklistTemplateController::class, 'store'])->name('checklist_templates.store');
    Route::get('/checklist_templates/{checklist_template}', [ChecklistTemplateController::class, 'show']);
    Route::get('/checklist_templates/{checklist_template}/edit', [ChecklistTemplateController::class, 'edit'])->name('checklist_templates.edit');
    Route::patch('/checklist_templates/{checklist_template}', [ChecklistTemplateController::class, 'update'])->name('checklist_templates.update');
    Route::delete('/checklist_templates/{checklist_template}', [ChecklistTemplateController::class, 'destroy']);

    //TaskTemplates
    Route::get('/task_templates/create', [TaskTemplateController::class, 'create'])->name('task_templates.create');
    Route::post('/task_templates', [TaskTemplateController::class, 'store'])->name('task_templates.store');
    Route::get('/task_templates/{task_template}/edit', [TaskTemplateController::class, 'edit']);
    Route::patch('/task_templates/{task_template}', [TaskTemplateController::class, 'update']);
    Route::delete('/task_templates/{task_template}', [TaskTemplateController::class, 'destroy']);

    //Tasks
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::get('/tasks/own', [TaskController::class, 'indexOwnTasks'])->name('tasks.own');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::put('/tasks/order', [TaskController::class, 'updateOrder']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    //Categories
    Route::get('/settings/projects', [CategoryController::class, 'index'])->name('project.settings');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    //Genres
    Route::post('/genres', [GenreController::class, 'store'])->name('genres.store');
    Route::patch('/genres/{genre}', [GenreController::class, 'update']);
    Route::delete('/genres/{genre}', [GenreController::class, 'destroy']);

    //Sectors
    Route::post('/sectors', [SectorController::class, 'store'])->name('sectors.store');
    Route::patch('/sectors/{sector}', [SectorController::class, 'update']);
    Route::delete('/sectors/{sector}', [SectorController::class, 'destroy']);

    //Comments
    Route::get('/comments/create', [CommentController::class, 'create'])->name('comments.create');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    //Areas
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.management');
    Route::get('/areas/trashed', [AreaController::class, 'getTrashed'])->name('areas.trashed');
    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::post('/areas/{area}/duplicate', [AreaController::class, 'duplicate'])->name('areas.duplicate');
    Route::patch('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy']);
    //Trash
    Route::delete('/areas/{id}/force', [AreaController::class, 'forceDelete'])->name('areas.force');
    Route::patch('/areas/{id}/restore', [AreaController::class, 'restore'])->name('areas.restore');

    //Rooms
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/trashed', [RoomController::class, 'getTrashed'])->name('rooms.trashed');
    Route::get('/rooms/free', [RoomController::class, 'getAllDayFree'])->name('rooms.free');
    Route::post('/rooms/{room}/duplicate', [RoomController::class, 'duplicate'])->name('rooms.duplicate');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::put('/rooms/order', [RoomController::class, 'updateOrder']);
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    //Trash
    Route::delete('/rooms/{id}/force', [RoomController::class, 'forceDelete'])->name('rooms.force');
    Route::patch('/rooms/{id}/restore', [RoomController::class, 'restore'])->name('rooms.restore');

    //RoomFiles
    Route::post('/rooms/{room}/files', [RoomFileController::class, 'store']);
    Route::get('/room_files/{room_file}', [RoomFileController::class, 'download'])->name('download_room_file');
    Route::delete('/room_files/{room_file}', [RoomFileController::class, 'destroy']);
    Route::delete('/room_files/{id}/force_delete', [RoomFileController::class, 'force_delete']);

    //Room Categories
    Route::post('/rooms/categories', [RoomCategoryController::class, 'store']);
    Route::delete('/rooms/categories/{roomCategory}', [RoomCategoryController::class, 'destroy']);

    //Room Attributes
    Route::post('/rooms/attributes', [RoomAttributeController::class, 'store']);
    Route::delete('/rooms/attributes/{roomAttribute}', [RoomAttributeController::class, 'destroy']);

    //Filters
    Route::get('/filters', [FilterController::class, 'index']);
    Route::post('/filters', [FilterController::class, 'store']);
    Route::delete('/filters/{filter}', [FilterController::class, 'destroy']);

    /**
     * Event Views
     */
    Route::get('/events/view', [EventController::class, 'viewEventIndex'])->name('events.view.index');
    Route::get('/events/requests', [EventController::class, 'viewRequestIndex'])->name('events.requests');
    Route::get('/dashboard', [EventController::class, 'showDashboardPage'])->name('dashboard');
    Route::get('/events/trashed', [EventController::class, 'getTrashed'])->name('events.trashed');

    /**
     * Event Api
     */
    Route::get('/events', [EventController::class, 'eventIndex'])->name('events.index');
    Route::get('/events/collision', [EventController::class, 'getCollisionCount'])->name('events.collisions');
    Route::get('/event/{event}', [EventController::class, 'getEventById'])->name('events.getById');
    Route::post('/events', [EventController::class, 'storeEvent'])->name('events.store');
    Route::put('/events/{event}', [EventController::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.delete');

    Route::put('/event/requests/{event}',[EventController::class, 'acceptEvent'])->name('events.accept');

    //Trash
    Route::delete('/events/{id}/force', [EventController::class, 'forceDelete'])->name('events.force');
    Route::patch('/events/{id}/restore', [EventController::class, 'restore'])->name('events.restore');

    //EventTypes
    Route::get('/event_types', [EventTypeController::class, 'index'])->name('event_types.management');
    Route::post('/event_types', [EventTypeController::class, 'store'])->name('event_types.store');
    Route::get('/event_types/{event_type}', [EventTypeController::class, 'show'])->name('event_types.show');
    Route::patch('/event_types/{event_type}', [EventTypeController::class, 'update'])->name('event_types.update');
    Route::delete('/event_types/{event_type}', [EventTypeController::class, 'destroy']);

    // notification
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');


    Route::post('/collision/room', [RoomController::class, 'collisionsCount'])->name('collisions.room');

    Route::patch('/notifications', [NotificationController::class, 'setOnRead'])->name('notifications.setReadAt');
    Route::patch('/user/settings/group', [NotificationController::class, 'toggleGroup'])->name('notifications.group');
    Route::patch('/user/settings/{setting}', [NotificationController::class, 'updateSetting'])->name('notifications.settings');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.delete');

    //globalNotification
    Route::get('/globalNotification', [GlobalNotificationController::class, 'show'])->name('global_notification.show');
    Route::post('/globalNotification/create', [GlobalNotificationController::class, 'store'])->name('global_notification.store');
    Route::delete('/globalNotification/{globalNotification}', [GlobalNotificationController::class, 'destroy'])->name('global_notification.destroy');

    //Contracts
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.management');
    Route::post('/projects/{project}/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/download', [ContractController::class, 'download'])->name('contracts.download');
    Route::patch('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy']);

    //ContractModules
    Route::get('/contract_modules', [ContractModuleController::class, 'index'])->name('contracts.management');
    Route::post('/contract_modules', [ContractModuleController::class, 'store'])->name('contracts.store');
    Route::get('/contract_modules/{module}/download', [ContractModuleController::class, 'download'])->name('contracts.download');
    Route::delete('/contract_modules/{module}', [ContractModuleController::class, 'destroy']);

});

// Money Sources
Route::post('/money_sources', [\App\Http\Controllers\MoneySourceController::class, 'store'])->name('money_sources.store');
