<?php

namespace App\Http\Controllers;

use App\Enums\NotificationConstEnum;
use App\Http\Requests\SearchRequest;
use App\Models\MoneySource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;


class MoneySourceController extends Controller
{
    protected ?NotificationController $notificationController = null;
    protected ?stdClass $notificationData = null;

    public function __construct()
    {
        $this->notificationController = new NotificationController();
        $this->notificationData = new \stdClass();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function index()
    {
        return inertia('MoneySources/Show', [
            'moneySources' => MoneySource::all(),
            'moneySourceGroups' => MoneySource::where('is_group',true)->get(),
        ]);
    }

    public function search(SearchRequest $request) {
        $filteredObjects = [];
        $this->authorize('viewAny',User::class);
        if($request->input('type') === 'single'){
            $moneySources = MoneySource::search($request->input('query'))->get();
            foreach ($moneySources as $moneySource){
                if($moneySource->is_group === 1 || $moneySource->is_group === true){
                    continue;
                }
                $filteredObjects[] = $moneySource;
            }

            return $filteredObjects;
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $inputArray = [];
        foreach ($request->users as $requestUser){
            $user = User::find($requestUser);
            $inputArray[$user->id] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'profile_photo_url' => $user->profile_photo_url
            ];
            // create user Notification
            $this->notificationData->type = NotificationConstEnum::NOTIFICATION_BUDGET_MONEY_SOURCE_AUTH_CHANGED;
            $this->notificationData->title = 'Du hast Zugriff auf "'. $request->name . '" erhalten';
            $this->notificationData->created_by = Auth::user();
            $broadcastMessage = [
                'id' => rand(1, 1000000),
                'type' => 'success',
                'message' => $this->notificationData->title
            ];
            $this->notificationController->create($user, $this->notificationData, $broadcastMessage);
        }

        if(!empty($request->amount)){
            $amount = str_replace(',', '.', $request->amount);
        } else {
            $amount = 0.00;
        }

        // user => Auth()::user()
        $user = Auth::user();
        $source = $user->money_sources()->create([
            'name' => $request->name,
            'amount' => $amount,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'source_name' => $request->source_name,
            'description' => $request->description,
            'is_group' => $request->is_group,
            'users' => json_encode($inputArray)
        ]);

        if($request->is_group){
            foreach ($request->sub_money_source_ids as $sub_money_source_id){
                $money_source = MoneySource::find($sub_money_source_id);
                $money_source->update(['group_id' => $source->id]);
            }
        }

        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\MoneySource  $moneySource
     * @return \Illuminate\Http\Response
     */
    public function show(MoneySource $moneySource)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MoneySource  $moneySource
     * @return \Illuminate\Http\Response
     */
    public function edit(MoneySource $moneySource)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MoneySource  $moneySource
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MoneySource $moneySource)
    {
        $oldMoneySourceTeam = $moneySource->users;
        // TODO: Update Values hier eintragen
        $moneySource->fill($request->only(''));
        $moneySource->save();

        $newMoneySourceTeam = $moneySource->users;

        $this->checkMoneySourceTeamChanges($moneySource->id, $oldMoneySourceTeam, $newMoneySourceTeam);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MoneySource  $moneySource
     * @return \Illuminate\Http\Response
     */
    public function destroy(MoneySource $moneySource)
    {
        //
    }


    private function checkMoneySourceTeamChanges($moneySourceId, $oldTeam, $newTeam)
    {

    }
}
