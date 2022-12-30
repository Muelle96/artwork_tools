<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractUpdateRequest;
use App\Http\Resources\ContractModuleResource;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractModule;
use App\Models\Project;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contract::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response|ResponseFactory
     */
    public function viewIndex()
    {
        $contracts = Contract::all();
        return inertia('Contracts/ContractManagement', [
            'contracts' => ContractResource::collection($contracts),
            'contract_modules' => ContractModuleResource::collection(ContractModule::all())
            ]);

    }

    public function index(Request $request)
    {

        $contracts = Contract::all();
        $costsFilter = json_decode($request->input('costsFilter'));
        $legalFormsFilter = json_decode($request->input('legalFormsFilter'));
        $contractTypesFilter = json_decode($request->input('contractTypesFilter'));

        if(count($costsFilter->array) != 0 || count($legalFormsFilter->array) != 0 || count($contractTypesFilter->array) != 0) {
            $legal_forms = collect($legalFormsFilter->array);
            $contract_types = collect($contractTypesFilter->array);
            $cost_filters = collect($costsFilter->array);

            Debugbar::info($legal_forms);
            Debugbar::info($cost_filters);

            if($cost_filters->contains('KSK-pflichtig')) {
                $contracts = $contracts->where('ksk_liable', true);
            }
            if($cost_filters->contains( 'Im Ausland ansässig')) {
                $contracts = $contracts->where('resident_abroad', true);
            }
            if(count($legal_forms) > 0) {
                $contracts = $contracts->whereIn('legal_form', $legal_forms);
            }
            if(count($contract_types) > 0) {
                $contracts = $contracts->whereIn('type', $contract_types);
            }
        }
        return [
            'contracts' => ContractResource::collection($contracts),
            'contract_modules' => ContractModuleResource::collection(ContractModule::all())
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param Contract $contract
     * @return Response|ResponseFactory
     */
    public function show(Contract $contract)
    {
        return inertia('Contracts/Contracts', [
            'contract' => new ContractResource($contract),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request, Project $project)
    {

        if (!Storage::exists("contracts")) {
            Storage::makeDirectory("contracts");
        }

        $file = $request->file('contract');
        $original_name = $file->getClientOriginalName();
        $basename = Str::random(20).$original_name;

        Storage::putFileAs('contracts', $file, $basename);

        $contract = $project->contracts()->create([
            'name' => $original_name,
            'basename' => $basename,
            'contract_partner' => $request->contract_partner,
            'amount' => $request->amount,
            'project_id' => $project->id,
            'description' => $request->description,
            'ksk_liable' => $request->ksk_liable,
            'resident_abroad' => $request->resident_abroad,
            'legal_form' => $request->legal_form,
            'type' => $request->type
        ]);

        $contract->accessing_users()->attach($request->accessibleUsers);

        $contract->save();

        return Redirect::back();
    }

    /**
     * Display the specified resource.
     *
     * @param Contract $contract
     * @return StreamedResponse
     * @throws AuthorizationException
     */
    public function download(Contract $contract): StreamedResponse
    {
        //$this->authorize('view contracts');

        return Storage::download('contracts/'. $contract->basename, $contract->name);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ContractUpdateRequest $request
     * @param Contract $contract
     * @return RedirectResponse
     */
    public function update(ContractUpdateRequest $request, Contract $contract)
    {
        $contract->fill($request->data());

        if ($request->get('accessibleUsers')) {
            $contract->accessing_users()->delete();
            $contract->accessing_users()->createMany($request->accessibleUsers);
        }

        if($request->file('contract')) {
            Storage::delete('contracts/'. $contract->basename);
            $file = $request->file('contract');
            $original_name = $file->getClientOriginalName();
            $basename = Str::random(20).$original_name;

            $contract->basename = $basename;
            $contract->name = $original_name;
            $contract->save();

            Storage::putFileAs('contracts', $file, $basename);
        }

        return Redirect::back();


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Contract $contract
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contract $contract)
    {
        $contract->delete();
        Redirect::back();
    }
}
