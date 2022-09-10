<?php

namespace App\Http\Controllers\Shopper;

use App\Http\Controllers\Controller;
use App\Models\Shopper\Shopper;
use App\Models\Shopper\Status;
use App\Models\Store\Location\Location;
use Illuminate\Http\Request;

class ShopperQueueController extends Controller
{

    // Support function for retreiving status IDs.
    private function getStatuses() {
        // Statuses must be configured in env variables, this can be comming from DB, but exists
        // the posibility that the names or initial status change in the future.
        $statuses = new \stdClass();
        $statuses->active = config('services.shopperqueue.status.active', 1);
        $statuses->completed = config('services.shopperqueue.status.completed', 2);
        $statuses->pending = config('services.shopperqueue.status.pending', 3);

        return $statuses;
    }

    private function getShoppersByLocation(Location $location, $statusId=null, $limit = null) {
        $shoppers = $location->shoppers();

        if(!is_null($statusId)) {
            $shoppers = $shoppers->where('status_id', $statusId);
        }

        if(!is_null($limit)) {
            $shoppers = $shoppers->limit($limit)
                ->orderByDesc('check_in');
        }

        return $shoppers;
    }

    public function checkIn(request $request) {

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'location_id' => 'required|integer|exists:locations,id'
        ]);

        $location = Location::find($validatedData['location_id']);
        $statuses = $this->getStatuses();
        
        $activeShoppers = $this->getShoppersByLocation($location, $statuses->active)->count();
        
        $shopper = new Shopper();
        $shopper->first_name = $validatedData['first_name'];
        $shopper->last_name = $validatedData['last_name'];
        $shopper->email = $validatedData['email'];
        $shopper->location_id = $validatedData['location_id'];
        $shopper->check_in = now();
        $shopper->status_id = $location->shopper_limit > $activeShoppers ? $statuses->active : $statuses->pending;

        try {
            $shopper->save();

            return response()->json([
                'shopper' => $shopper
            ], 200);
        }catch(\Exception $e) {
            if (env('APP_DEBUG', true)) {
                $response['message'] = $e->getMessage();
                $response['payload'] = [
                    'shopper' => $shopper
                ];
            } else {
                $response['message'] = 'Something went wrong, please try again';
            }

            return response()->json($response, 500);
        }
    }

    public function checkOut(Request $request) {

        $validatedData = $request->validate([
            'shopper_id' => 'required|exists:shoppers,id',
            'user_id' => 'required|exists:users,id'
        ]);

        $shopper = Shopper::find($validatedData['shopper_id']);
        $shopper->user_id = $validatedData['user_id'];
        $shopper->status_id = $this->getStatuses()->completed;
        $shopper->check_out = now();

        try {
            $shopper->save();

            $this->fillUpLocation($shopper->location());

            return response()->json([
                'shopper' => $shopper
            ], 200);
        }catch(\Exception $e) {
            if (env('APP_DEBUG', true)) {
                $response['message'] = $e->getMessage();
                $response['payload'] = [
                    'shopper' => $shopper
                ];
            } else {
                $response['message'] = 'Something went wrong, please try again';
            }

            return response()->json($response, 500);
        }
    }

    public function refreshQueue(Request $request, $location_uuid) {

        $location = Location::where(['uuid' => $location_uuid])->first();

        try {
            $checkedIn = $this->fillUpLocation($location);

            return response()->json([
                'shoppers' => [
                    'checked_in' => $checkedIn ?? 0
                ]
            ], 200);
        } catch(\Exception $e) {
            if (env('APP_DEBUG', true)) {
                $response['message'] = $e->getMessage();
            } else {
                $response['message'] = 'Something went wrong, please try again';
            }

            return response()->json($response, 500);
        }
    }

    public function fillUpLocation($location) {
        $statuses = $this->getStatuses();

        $activeShoppers = $this->getShoppersByLocation($location, $statuses->active)->count();

        $allowedShoppers = $location->shopper_limit - $activeShoppers;
        $allowedShoppers = $allowedShoppers < 0 ? 0 : $allowedShoppers;

        if($allowedShoppers > 0) {
            $nextShoppers = $this->getShoppersByLocation($location, $statuses->pending, $allowedShoppers);
            return $nextShoppers->update(['status_id' => $statuses->active]);
        }

        return 0;
    }
}
