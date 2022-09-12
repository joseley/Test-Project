<?php

namespace App\Http\Controllers\Store\Location;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Location\LocationCreateRequest;
use App\Http\Requests\Store\Location\LocationQueueRequest;
use App\Http\Requests\Store\Location\LocationStoreRequest;
use App\Http\Requests\Store\Location\LocationUpdateRequest;
use App\Models\Store\Location\Location;
use App\Services\Store\Location\LocationService;

/**
 * Class LocationController
 * @package App\Http\Controllers\Store
 */
class LocationController extends Controller
{
    /**
     * @var LocationService
     */
    protected $location;

    /**
     * LocationController constructor.
     * @param LocationService $location
     */
    public function __construct(LocationService $location)
    {
        $this->location = $location;
    }

    /**
     * @param Location $location
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function public(Location $location)
    {
        return view('stores.location.public')
            ->with('location', $location);
    }

    /**
     * @param LocationCreateRequest $request
     * @param string $storeUuid
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create(LocationCreateRequest $request, string $storeUuid)
    {
        return view('stores.location.create')
            ->with('store', $storeUuid);
    }

    /**
     * @param LocationStoreRequest $request
     * @param string $storeUuid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LocationStoreRequest $request, string $storeUuid): \Illuminate\Http\RedirectResponse
    {
        $this->location->create([
            'location_name' => $request->location_name,
            'shopper_limit' => $request->shopper_limit,
            'store_id' => $storeUuid
        ]);

        return redirect()->route('store.store', ['store' => $storeUuid]);
    }

    /**
     * @param LocationQueueRequest $request
     * @param string $storeUuid
     * @param string $locationUuid
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function queue(LocationQueueRequest $request, string $storeUuid, string $locationUuid)
    {
        $location = $this->location->show(
            [
                'uuid' => $locationUuid
            ],
            [
                'Shoppers',
                'Shoppers.Status'
            ]
        );

        $shoppers = null;

        if( isset($location['shoppers']) && count($location['shoppers']) >= 1 ){
            $shoppers = $this->location->getShoppers($location['shoppers']);
        }

        return view('stores.location.queue')
            ->with('location', $location)
            ->with('shoppers', $shoppers);
    }

    public function editView(LocationUpdateRequest $request, $storeUuid, $locationUuid) {
        $location = Location::where('uuid', $locationUuid)->firstOrFail();

        return view('stores.location.edit')->with('location', $location);
    }

    public function edit(LocationUpdateRequest $request, $storeUuid, $locationUuid) {
        $this->update($locationUuid, $request->all());

        return redirect()->route('store.store', ['store'=>$storeUuid]);
    }

    public function apiUpdate(LocationUpdateRequest $request, $storeUuid, $locationUuid) {
        return response()->json($this->update($locationUuid, $request->all()), 200);
    }

    public function update($locationUuid, $newData) {
        $location = Location::where('uuid', $locationUuid)->firstOrFail();

        $location->location_name = $newData['location_name'] ?? $location->location_name;
        $location->shopper_limit = $newData['shopper_limit'] ?? $location->shopper_limit;
        $location->store_id = $newData['store_id'] ?? $location->store_id;

        $location->save();

        return $location;
    }
}
