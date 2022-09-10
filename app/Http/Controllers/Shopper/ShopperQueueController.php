<?php

namespace App\Http\Controllers\Shopper;

use App\Http\Controllers\Controller;
use App\Models\Shopper\Shopper;
use App\Models\Shopper\Status;
use App\Models\Store\Location\Location;
use Illuminate\Http\Request;

class ShopperQueueController extends Controller
{
    public function checkIn(request $request) {

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'location_id' => 'required|integer|exists:locations,id'
        ]);

        $location = Location::find($validatedData['location_id']);
        
        // Statuses must be configured in env variables, this can be comming from DB, but exists
        // the posibility the names or initial status change
        $statuses = new \stdClass();
        $statuses->active = config('services.shopperqueue.status.active', 1);
        $statuses->pending = config('services.shopperqueue.status.pending', 3);
        
        $activeShoppers = Shopper::where('status_id', $statuses->active)
            ->where('location_id', $location->id)
            ->count();
        
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
            $response['message'] = env('APP_DEBUG', true) ? $e->getMessage() : 'Something went wrong, please try again';
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
        $shopper->status_id = config('services.shopperqueue.status.completed', 2);
        $shopper->check_out = now();

        try {
            $shopper->save();

            return response()->json([
                'shopper' => $shopper
            ], 200);
        }catch(\Exception $e) {
            $response['message'] = env('APP_DEBUG', true) ? $e->getMessage() : 'Something went wrong, please try again';
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
}
