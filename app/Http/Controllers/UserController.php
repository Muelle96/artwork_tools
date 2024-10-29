<?php

namespace App\Http\Controllers;

use Artwork\Core\Http\Requests\SearchRequest;
use Artwork\Modules\Calendar\Services\CalendarService;
use Artwork\Modules\Craft\Models\Craft;
use Artwork\Modules\Craft\Services\CraftService;
use Artwork\Modules\Department\Models\Department;
use Artwork\Modules\Event\Enum\ShiftPlanWorkerSortEnum;
use Artwork\Modules\Event\Models\Event;
use Artwork\Modules\Event\Services\EventService;
use Artwork\Modules\EventType\Services\EventTypeService;
use Artwork\Modules\Freelancer\Models\Freelancer;
use Artwork\Modules\Invitation\Models\Invitation;
use Artwork\Modules\Permission\Enums\PermissionEnum;
use Artwork\Modules\Permission\Models\Permission;
use Artwork\Modules\PermissionPresets\Services\PermissionPresetService;
use Artwork\Modules\Project\Services\ProjectService;
use Artwork\Modules\Role\Enums\RoleEnum;
use Artwork\Modules\Room\Models\Room;
use Artwork\Modules\Room\Services\RoomService;
use Artwork\Modules\ServiceProvider\Models\ServiceProvider;
use Artwork\Modules\Shift\Enums\ShiftTabSort;
use Artwork\Modules\ShiftQualification\Http\Requests\UpdateUserShiftQualificationRequest;
use Artwork\Modules\ShiftQualification\Repositories\ShiftQualificationRepository;
use Artwork\Modules\ShiftQualification\Services\ShiftQualificationService;
use Artwork\Modules\ShiftQualification\Services\UserShiftQualificationService;
use Artwork\Modules\User\Enums\MemberSortEnum;
use Artwork\Modules\User\Enums\UserSortEnum;
use Artwork\Modules\User\Events\UserUpdated;
use Artwork\Modules\User\Http\Requests\MembersManagementRequest;
use Artwork\Modules\User\Http\Resources\MinimalUserIndexResource;
use Artwork\Modules\User\Http\Resources\UserIndexResource;
use Artwork\Modules\User\Http\Resources\UserShowResource;
use Artwork\Modules\User\Http\Resources\UserWorkProfileResource;
use Artwork\Modules\User\Models\User;
use Artwork\Modules\User\Services\UserService;
use Artwork\Modules\UserUserManagementSetting\Services\UserUserManagementSettingService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spatie\Permission\Models\Role;
use Throwable;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    /**
     * @return array<string, mixed>
     * @throws AuthorizationException
     */
    public function search(SearchRequest $request): array
    {
        return UserIndexResource::collection(User::nameOrLastNameLike($request->get('query'))->get())->resolve();
    }


    public function scoutSearch(Request $request, UserService $userService): JsonResponse
    {
        $users = [];
        if (
            request()->has('user_search') &&
            request()->get('user_search') !== null &&
            request()->get('user_search') !== ''
        ) {
            $users = $userService->searchUsers($request->string('user_search'));
        }

        return \response()->json($users);
    }

    /**
     * @param SearchRequest $request
     * @return User[]
     */
    public function moneySourceSearch(SearchRequest $request): array
    {
        $wantedUserArray = [];

        $wantedUsers = User::search($request->input('query'))->get();
        foreach ($wantedUsers as $user) {
            $wantedUserArray[] = $user;
        }
        return $wantedUserArray;
    }

    /**
     * @return Application|RedirectResponse|mixed
     * @throws AuthorizationException
     */
    public function resetUserPassword(Request $request): mixed
    {
        $this->authorize('update', User::class);

        $request->validate([Fortify::email() => 'required|email']);

        $status = Password::broker()->sendResetLink(
            $request->only(Fortify::email())
        );

        return $status == Password::RESET_LINK_SENT
            ? Redirect::back()
                ->with('status', __('passwords.sentToUser', [], Auth::user()->language))
            : app(FailedPasswordResetLinkRequestResponse::class, ['status' => $status]);
    }


    public function resetPassword(): Response|ResponseFactory
    {
        $token = request('token');
        $email = request('email');

        return inertia('Auth/ResetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function index(
        PermissionPresetService $permissionPresetService,
        UserUserManagementSettingService $userUserManagementSettingService,
        UserService $userService,
        MembersManagementRequest $request
    ): Response|ResponseFactory {
        $saveFilterAndSort = $request->boolean('saveFilterAndSort');
        $userUserManagementSetting = $userUserManagementSettingService
            ->getFromUser($userService->getAuthUser())
            ->getAttribute('settings');
        $sortEnum = $saveFilterAndSort ?
            $request->enum('sort', UserSortEnum::class) :
            (
                $userUserManagementSetting['sort_by'] ?
                    UserSortEnum::from($userUserManagementSetting['sort_by']) :
                    null
            );
        $searchQuery = $request->get('query');

        $users = MinimalUserIndexResource::collection(
            !empty($searchQuery)
                ? User::search($searchQuery)
                ->query(function ($query) use ($sortEnum): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo']);

                    // Sortierung nur anwenden, wenn $sortEnum vorhanden ist
                    if (!is_null($sortEnum)) {
                        switch ($sortEnum) {
                            case UserSortEnum::ALPHABETICALLY_ASCENDING:
                            case UserSortEnum::ALPHABETICALLY_DESCENDING:
                                $columns = $sortEnum->mapToColumn();
                                $dir = $sortEnum->mapToDirection();
                                $query->orderBy($columns[0], $dir);
                                $query->orderBy($columns[1], $dir);
                                break;
                            case UserSortEnum::CHRONOLOGICALLY_ASCENDING:
                            case UserSortEnum::CHRONOLOGICALLY_DESCENDING:
                                $query->orderBy($sortEnum->mapToColumn(), $sortEnum->mapToDirection());
                                break;
                        }
                    }
                })
                ->get()
                : User::query()
                ->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo'])
                ->when(!is_null($sortEnum), function ($query) use ($sortEnum): void {
                    switch ($sortEnum) {
                        case UserSortEnum::ALPHABETICALLY_ASCENDING:
                        case UserSortEnum::ALPHABETICALLY_DESCENDING:
                            $columns = $sortEnum->mapToColumn();
                            $dir = $sortEnum->mapToDirection();
                            $query->orderBy($columns[0], $dir);
                            $query->orderBy($columns[1], $dir);
                            break;
                        case UserSortEnum::CHRONOLOGICALLY_ASCENDING:
                        case UserSortEnum::CHRONOLOGICALLY_DESCENDING:
                            $query->orderBy($sortEnum->mapToColumn(), $sortEnum->mapToDirection());
                            break;
                    }
                })
                ->get()
        )->resolve();


        if ($saveFilterAndSort) {
            $userUserManagementSettingService->updateOrCreateIfNecessary(
                $userService->getAuthUser(),
                [
                    'sort_by' => $sortEnum?->name,
                ]
            );
        }

        return inertia('Users/Index', [
            'users' => $users,
            'all_permissions' => Permission::all()->groupBy('group'),
            'departments' => Department::all(),
            'roles' => Role::all(),
            'freelancers' => Freelancer::all(),
            'serviceProviders' => ServiceProvider::query()->without('contacts')->get(),
            'permission_presets' => $permissionPresetService->getPermissionPresets(),
            'invitedUsers' => Invitation::all(),
            'userSortEnumNames' => array_map(
                static function (UserSortEnum $enum): string {
                    return $enum->name;
                },
                UserSortEnum::cases()
            ),
            'userUserManagementSetting' => $userUserManagementSettingService
                ->getFromUser($userService->getAuthUser())
                ->getAttribute('settings')
        ]);
    }

    //@todo: fix phpcs error - refactor function because complexity exceeds allowed maximum
    //phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded
    //phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
    public function getAddresses(
        UserUserManagementSettingService $userUserManagementSettingService,
        UserService $userService,
        MembersManagementRequest $request
    ): Response|ResponseFactory {
        $saveFilterAndSort = $request->boolean('saveFilterAndSort');
        $userUserManagementSetting = $userUserManagementSettingService
            ->getFromUser($userService->getAuthUser())
            ->getAttribute('settings');
        $sortEnum = $saveFilterAndSort ? $request->enum('sort', MemberSortEnum::class) :
            ($userUserManagementSetting['sort_by'] ?
                UserSortEnum::from($userUserManagementSetting['sort_by']) : null);

        $freelancers = Freelancer::query()->when(
            strlen($search = $request->string('query')) > 0,
            function (Builder $builder) use ($search): void {
                $builder->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%');
            }
        )->when(
            !is_null($sortEnum),
            function (Builder $builder) use ($sortEnum): void {
                switch ($sortEnum) {
                    case MemberSortEnum::ALPHABETICALLY_ASCENDING:
                    case MemberSortEnum::ALPHABETICALLY_DESCENDING:
                        $columns = $sortEnum->mapToColumn(1);
                        $dir = $sortEnum->mapToDirection();
                        $builder->orderBy($columns[0], $dir);
                        $builder->orderBy($columns[1], $dir);
                        break;
                    case MemberSortEnum::CHRONOLOGICALLY_ASCENDING:
                    case MemberSortEnum::CHRONOLOGICALLY_DESCENDING:
                        $builder->orderBy($sortEnum->mapToColumn(1), $sortEnum->mapToDirection());
                        break;
                }
            }
        )->get();

        $serviceProviders = ServiceProvider::query()->without(['contacts'])->when(
            strlen($search = $request->string('query')) > 0,
            function (Builder $builder) use ($search): void {
                $builder->where('provider_name', 'like', '%' . $search . '%');
            }
        )->when(
            !is_null($sortEnum),
            function (Builder $builder) use ($sortEnum): void {
                switch ($sortEnum) {
                    case MemberSortEnum::ALPHABETICALLY_ASCENDING:
                    case MemberSortEnum::ALPHABETICALLY_DESCENDING:
                    case MemberSortEnum::CHRONOLOGICALLY_ASCENDING:
                    case MemberSortEnum::CHRONOLOGICALLY_DESCENDING:
                        $builder->orderBy($sortEnum->mapToColumn(2), $sortEnum->mapToDirection());
                        break;
                }
            }
        )->get();

        if ($saveFilterAndSort) {
            $userUserManagementSettingService->updateOrCreateIfNecessary(
                $userService->getAuthUser(),
                [
                    'sort_by' => $sortEnum?->name,
                ]
            );
        }

        return inertia('Users/Addresses', [
            'freelancers' => $freelancers,
            'serviceProviders' => $serviceProviders,
            'memberSortEnums' => array_map(
                function (MemberSortEnum $enum): string {
                    return $enum->name;
                },
                MemberSortEnum::cases()
            ),
            'userUserManagementSetting' => $userUserManagementSettingService
                ->getFromUser($userService->getAuthUser())
                ->getAttribute('settings')
        ]);
    }

    public function editUserInfo(User $user): Response|ResponseFactory
    {
        return inertia('Users/UserInfoPage', [
            'user_to_edit' => new UserShowResource($user),
            'currentTab' => 'info',
            "departments" => Department::all(),
            "password_reset_status" => session('status'),
            'calendar_settings' => $user->calendar_settings,
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function editUserShiftPlan(
        Request $request,
        User $user,
        UserService $userService,
        ShiftQualificationService $shiftQualificationService,
        CalendarService $calendarService,
        EventService $eventService,
        RoomService $roomService,
        EventTypeService $eventTypeService,
        ProjectService $projectService,
        SessionManager $sessionManager,
        Repository $config
    ): Response|ResponseFactory {
        $showVacationsAndAvailabilities = $request->get('showVacationsAndAvailabilities');
        $vacationMonth = $request->get('vacationMonth');
        $selectedDate = $showVacationsAndAvailabilities ?
            Carbon::parse($showVacationsAndAvailabilities) :
            Carbon::today();
        $selectedPeriodDate = $vacationMonth ?
            Carbon::parse($vacationMonth) :
            Carbon::today();

        $selectedPeriodDate->locale($sessionManager->get('locale') ?? $config->get('app.fallback_locale'));

        return Inertia::render(
            'Users/UserShiftPlanPage',
            $userService->getUserShiftPlanPageDto(
                $user,
                $calendarService,
                $eventService,
                $roomService,
                $eventTypeService,
                $projectService,
                $shiftQualificationService,
                $selectedPeriodDate,
                $selectedDate,
                $request->get('month'),
                $vacationMonth
            )
        );
    }

    public function editUserTerms(User $user): Response|ResponseFactory
    {
        return inertia('Users/UserTermsPage', [
            'user_to_edit' => new UserShowResource($user),
            'currentTab' => 'terms',
        ]);
    }

    public function editUserPermissions(User $user): Response|ResponseFactory
    {
        return inertia('Users/UserPermissionsPage', [
            'user_to_edit' => new UserShowResource($user),
            'available_roles' => Role::all(),
            "all_permissions" => Permission::all()->groupBy('group'),
            'currentTab' => 'permissions',
        ]);
    }

    public function editUserWorkProfile(
        User $user,
        ShiftQualificationRepository $shiftQualificationRepository,
        CraftService $craftService
    ): Response|ResponseFactory {
        return inertia(
            'Users/UserWorkProfilePage',
            [
                'userToEdit' => (new UserWorkProfileResource(
                    $user,
                    $craftService->getAll()
                ))->resolve(),
                'currentTab' => 'workProfile',
                'shiftQualifications' => $shiftQualificationRepository->getAllAvailableOrderedByCreationDateAscending()
            ]
        );
    }

    public function updateUserPhoto(User $user, Request $request): void
    {
        if (isset($request['photo'])) {
            $user->updateProfilePhoto($request['photo']);
        }
    }

    /**
     * @param User $user
     * @param $month
     * @return array<string, mixed>
     */
    private function getAvailabilityData(User $user, $month = null): array
    {
        $vacationDays = $user->vacations()->orderBy('date', 'ASC')->get();

        $currentMonth = Carbon::now()->startOfMonth();

        if ($month) {
            $currentMonth = Carbon::parse($month)->startOfMonth();
        }

        $startDate = $currentMonth->copy()->startOfWeek();
        $endDate = $currentMonth->copy()->endOfMonth()->endOfWeek();

        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $onVacation = false;
            $weekNumber = $currentDate->weekOfYear;
            $day = $currentDate->day;
            foreach ($vacationDays as $vacationDay) {
                if ($currentDate->isSameDay($vacationDay->date)) {
                    $onVacation = true;
                }
            }

            if (!isset($calendarData[$weekNumber])) {
                $calendarData[$weekNumber] = ['weekNumber' => $weekNumber, 'days' => []];
            }

            $notInMonth = !$currentDate->isSameMonth($currentMonth);

            $calendarData[$weekNumber]['days'][] = [
                'day' => $day,
                'notInMonth' => $notInMonth,
                'onVacation' => $onVacation,
                'day_formatted' => $currentDate->format('Y-m-d'),
            ];

            $currentDate->addDay();
        }

        $dateToShow = [
            $currentMonth->locale(\session()->get('locale') ?? config('app.fallback_locale'))->isoFormat('MMMM YYYY'),
            $currentMonth->copy()->startOfMonth()->toDate()
        ];

        return [
            'calendarData' => array_values($calendarData),
            'dateToShow' => $dateToShow
        ];
    }

    public function updateUserDetails(Request $request, User $user): RedirectResponse
    {
        if ($user->id !== Auth::user()->id && !Auth::user()->can(PermissionEnum::TEAM_UPDATE->value)) {
            abort(\Illuminate\Http\Response::HTTP_FORBIDDEN);
        }
        $user->update(
            $request->only(
                'first_name',
                'last_name',
                'phone_number',
                'position',
                'business',
                'description',
                'email',
                'language'
            )
        );

        $user->calendar_settings->update([
            'high_contrast' => $request->get('high_contrast')
        ]);

        if (Auth::user()->can(PermissionEnum::TEAM_UPDATE->value)) {
            $user->departments()->sync(
                collect($request->departments)
                    ->map(function ($department) {
                        return $department['id'];
                    })
            );
        }

        Session::put('locale', $user->language);

        return Redirect::back();
    }

    public function updateUserPermissionsAndRoles(Request $request, User $user): RedirectResponse
    {
        //only add permissions which are also existing to the array which gets synced with user
        $availablePermissions = PermissionEnum::cases();
        $permissionsToGrant = [];
        foreach ($request->permissions as $permissionToGrant) {
            foreach ($availablePermissions as $availablePermission) {
                if ($availablePermission->value === $permissionToGrant) {
                    $permissionsToGrant[] = $permissionToGrant;
                }
            }
        }

        //only add roles which are also existing to the array which gets synced with user
        $availableRoles = RoleEnum::cases();
        $rolesToGrant = [];
        foreach ($request->roles as $roleToGrant) {
            foreach ($availableRoles as $availableRole) {
                if ($availableRole->value === $roleToGrant) {
                    $rolesToGrant[] = $roleToGrant;
                }
            }
        }

        $user->syncPermissions($permissionsToGrant);
        $user->syncRoles($rolesToGrant);

        return Redirect::back();
    }

    public function updateChecklistStatus(Request $request): RedirectResponse
    {
        Auth::user()->update([
            'opened_checklists' => $request->opened_checklists
        ]);

        return Redirect::back();
    }

    public function updateAreaStatus(Request $request): RedirectResponse
    {
        Auth::user()->update([
            'opened_areas' => $request->opened_areas
        ]);

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function updateWorkProfile(User $user, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', User::class);

        $user->update([
            'work_name' => $request->get('workName'),
            'work_description' => $request->get('workDescription'),
            'is_freelancer' => $request->get('is_freelancer')
        ]);

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function updateCraftSettings(User $user, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', User::class);

        $user->update([
            'can_work_shifts' => $request->boolean('canBeAssignedToShifts'),
        ]);

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function updateShiftQualification(
        User $user,
        UpdateUserShiftQualificationRequest $request,
        UserShiftQualificationService $userShiftQualificationService
    ): RedirectResponse {
        $this->authorize('updateWorkProfile', User::class);

        if ($request->boolean('create')) {
            //if useable is set to true create a new entry in pivot table
            $userShiftQualificationService->createByRequestForUser($request, $user);
        } else {
            //if useable is set to false pivot table entry needs to be deleted
            $userShiftQualificationService->deleteByRequestForUser($request, $user);
        }

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function assignCraft(User $user, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', User::class);

        $user->assignedCrafts()->attach($request->get('craftId'));

        /*$craftToAssign = Craft::find();

        if (is_null($craftToAssign)) {
            return Redirect::back();
        }

        if (!$user->assignedCrafts->contains($craftToAssign)) {
            $user->assignedCrafts()->attach(Craft::find($request->get('craftId')));
        }*/

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function removeCraft(User $user, Craft $craft): RedirectResponse
    {
        $this->authorize('updateWorkProfile', User::class);

        $user->assignedCrafts()->detach($craft);

        return Redirect::back();
    }

    public function destroy(
        User $user,
        RoomService $roomService,
        EventService $eventService,
        UserService $userService
    ): RedirectResponse {
        $user->departments()->detach();
        $user->createdRooms()->withTrashed()->each(
            fn(Room $room) => $roomService->update(
                $room,
                ['user_id' => $userService->getAuthUserId()]
            )
        );
        $user->events()->withTrashed()->each(
            fn(Event $event) => $eventService->update(
                $event,
                ['user_id' => $userService->getAuthUserId()]
            )
        );
        $user->delete();

        broadcast(new UserUpdated())->toOthers();

        return Redirect::route('users');
    }


    public function temporaryUserUpdate(User $user, Request $request): void
    {
        $user->update($request->only([
            'temporary',
            'employStart',
            'employEnd'
        ]));
    }


    /**
     * @throws AuthorizationException
     */
    public function updateUserTerms(User $user, Request $request): void
    {
        $this->authorize('updateTerms', User::class);

        $user->update($request->only([
            'weekly_working_hours',
            'salary_per_hour',
            'salary_description',
        ]));
    }


    public function updateCalendarSettings(User $user, Request $request): void
    {
        $user->calendar_settings()->update($request->only([
            'project_status',
            'options',
            'project_management',
            'repeating_events',
            'work_shifts',
            'description',
            'event_name',
            'high_contrast',
            'expand_days'
        ]));
    }

    public function updateSidebar(User $user, Request $request): void
    {
        $user->update($request->only([
            'is_sidebar_opened'
        ]));
    }

    public function updateChecklistStyle(User $user, Request $request): void
    {
        $user->update($request->only([
            'checklist_style'
        ]));
    }

    public function updateZoomFactor(User $user, Request $request): void
    {
        $user->update($request->only('zoom_factor'));
    }

    public function updateAtAGlance(User $user, Request $request): void
    {
        $user->update($request->only('at_a_glance'));
    }

    public function updateBulkSortId(User $user, Request $request): void
    {
        $user->update($request->only('bulk_sort_id'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function operationPlan(
        Request $request,
        User $user,
        UserService $userService,
        ShiftQualificationService $shiftQualificationService,
        CalendarService $calendarService,
        EventService $eventService,
        RoomService $roomService,
        EventTypeService $eventTypeService,
        ProjectService $projectService,
        SessionManager $sessionManager,
        Repository $config
    ): Response|ResponseFactory {
        $showVacationsAndAvailabilities = $request->get('showVacationsAndAvailabilities');
        $vacationMonth = $request->get('vacationMonth');
        $selectedDate = $showVacationsAndAvailabilities ?
            Carbon::parse($showVacationsAndAvailabilities) :
            Carbon::today();
        $selectedPeriodDate = $vacationMonth ?
            Carbon::parse($vacationMonth) :
            Carbon::today();
        $user->load(['shiftCalendarAbo']);
        $selectedPeriodDate->locale($sessionManager->get('locale') ?? $config->get('app.fallback_locale'));

        return Inertia::render(
            'Shifts/UserOperationPlan',
            $userService->getUserShiftPlanPageDto(
                $user,
                $calendarService,
                $eventService,
                $roomService,
                $eventTypeService,
                $projectService,
                $shiftQualificationService,
                $selectedPeriodDate,
                $selectedDate,
                $request->get('month'),
                $vacationMonth
            )
        );
    }

    public function compactMode(User $user, Request $request): void
    {
        $user->update($request->only('compact_mode'));
    }

    public function updateShowCrafts(User $user, Request $request): void
    {
        $user->update($request->only('show_crafts'));
    }

    public function calendarGoToStepper(User $user, Request $request): void
    {
        $user->update($request->only('goto_mode'));
    }

    /**
     * @throws Throwable
     */
    public function updateShiftPlanUserSortBy(
        User $user,
        Request $request
    ): void {
        $request->validate(
            [
                'sortBy' => [
                    'nullable',
                    Rule::enum(ShiftPlanWorkerSortEnum::class)
                ]
            ]
        );


        $user->updateOrFail([
            'shift_plan_user_sort_by_id' => $request->enum(
                'sortBy',
                ShiftPlanWorkerSortEnum::class
            )
        ]);
    }

    public function updateShiftTabUserSortBy(
        User $user,
        Request $request
    ): void {
        $request->validate(
            [
                'sortBy' => [
                    'nullable',
                    Rule::enum(ShiftTabSort::class)
                ]
            ]
        );


        $user->updateOrFail([
            'sort_type_shift_tab' => $request->enum(
                'sortBy',
                ShiftTabSort::class
            )
        ]);
    }

    public function updateUserOverviewHeight(User $user, Request $request): void
    {
        $user->update($request->only('drawer_height'));
    }
}
