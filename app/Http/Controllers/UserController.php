<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponseTrait;
    /**
     * Upgrade user to premium (duration-based or until specific date).
     */
    public function upgradeToPremium(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Validate incoming request
        $request->validate([
            'duration_months' => 'nullable|integer|min:1',
            'until_date'      => 'nullable|date|after:today',
        ]);

        if ($request->filled('until_date')) {
            $newUntil = Carbon::parse($request->until_date);

            if ($user->premium_until === null || $newUntil->greaterThan($user->premium_until)) {
                $user->premium_until = $newUntil;
            }
        } elseif ($request->filled('duration_months')) {

            $now = Carbon::now();

            if ($user->premium_until !== null && $user->premium_until->isFuture()) {
                $user->premium_until = $user->premium_until->addMonths($request->duration_months);
            } else {
                $user->premium_until = $now->addMonths($request->duration_months);
            }
        } else {
            return $this->error('Please provide duration_months or until_date.', 422);
        }

        $user->save();

        return $this->success([
            'premium_until' => $user->premium_until,
        ], 'User upgraded to premium.');
    }


    /**
     * Downgrade a user from premium.
     */
    public function downgrade($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->premium_until = null;
        $user->save();

        return $this->success('User downgraded from premium.');
    }

    /**
     * Ban a user.
     */
    public function ban($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->banned_at = Carbon::now();
        $user->save();

        return $this->success('User has been banned.');
    }

    /**
     * Lift ban from a user.
     */
    public function liftBan($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->banned_at = null;
        $user->save();

        return $this->success('User has been lifted from ban.');
    }

    public function profile(){
        return $this->success(Auth::user(), 'User information');
    }

    public function updateUsername(Request $request){
        $user = Auth::user();
        $user->name = $request->name;
        $user->save();
        return $this->success($user, 'Username updated successfully');
    }
}
