<?php

namespace App\Http\Controllers\Shopper;

use App\Http\Controllers\Controller;
use App\Models\Shopper\Shopper;
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

    public function store(Request $request, $location) {
        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
        ]);

        $validatedData['location_uuid'] = $location;

        try {
            $this->checkIn($validatedData);

            $success = true;
        } catch(\Exception $e) {
            $success = false;
        }

        return redirect()->route('public.location', $location);
    }

    public function apiCheckIn(Request $request) {
        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'location_uuid' => 'required|string|exists:locations,uuid',
        ]);

        try {
            $shopper = $this->checkIn($validatedData);

            return response()->json([
                'shopper' => $shopper
            ], 200);
        }catch(\Exception $e) {
            if (env('APP_DEBUG', true)) {
                $response['message'] = $e->getMessage();
                $response['payload'] = [
                    'shopper' => $response
                ];
            } else {
                $response['message'] = 'Something went wrong, please try again';
            }

            return response()->json($response, 500);
        }
    }

    public function checkIn($checkInData) {
        $location = Location::where('uuid', $checkInData['location_uuid'])->firstOrFail();
        $statuses = $this->getStatuses();

        $activeShoppers = $this->getShoppersByLocation($location, $statuses->active)->count();

        $shopper = new Shopper();
        $shopper->first_name = $checkInData['first_name'];
        $shopper->last_name = $checkInData['last_name'];
        $shopper->email = $checkInData['email'];
        $shopper->location_id = $location->id;
        $shopper->check_in = now();
        $shopper->status_id = $location->shopper_limit > $activeShoppers ? $statuses->active : $statuses->pending;

        $shopper->save();

        return $shopper;
    }

    public function apiCheckOut(Request $request) {
        $validatedData = $request->validate([
            'shopper_id' => 'required|exists:shoppers,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $data = $request->all();

        try {
            $shopper = $this->checkOut($data);

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

    public function checkOut($checkOutData) {
        $shopper = Shopper::find($checkOutData['shopper_id']);
        $shopper->user_id = $checkOutData['user_id'];
        $shopper->status_id = $this->getStatuses()->completed;
        $shopper->check_out = now();

        try {
            $shopper->save();

            $this->fillUpQueue($shopper->location());

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

    public function refreshQueue(Request $request) {
        $validatedData = $request->validate([
            'locationUuid' => 'required|string|exists:locations,uuid',
        ]);

        $location = Location::where(['uuid' => $validatedData['locationUuid']])->first();

        try {
            $checkedOut = $this->autoCheckOut($location);
            $checkedIn = $this->fillUpQueue($location);

            return response()->json([
                'shoppers' => [
                    'checked_out' => $checkedOut ?? 0,
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

    public function fillUpQueue(Location $location) {
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

    public function autoCheckOut(Location $location) {
        $statuses = $this->getStatuses();

        $slowShoppers = $this->getShoppersByLocation($location, $statuses->active)
            ->where('check_in', '<=', date('Y-m-d H:i:s', strtotime("-2 hours")));

        return $slowShoppers->update(['status_id' => $statuses->completed]);
    }
}
