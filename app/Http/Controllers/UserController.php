<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $order = $request->get('order', 'asc');
        $field = $request->get('field', 'id');
        $search = $request->get('search', '');

        $query = User::query()->with('roles');

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
            $query->orWhere('email', 'like', "%{$search}%");
        }

        $filtered = $query->count();

        $users = $query->orderBy($field, $order)
            ->skip($start)
            ->take($length)
            ->get()->each(function ($user) {
                $user->roles->each->makeHidden('pivot');
            });;

        $total = User::count();

        return $this->success([
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $users,
        ], 'Users retrieved successfully');
    }

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
        $validated = request()->validate([
            'ban_reason' => 'required|string',
        ]);
        $user = User::find($id);
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->banned_at = Carbon::now();
        $user->ban_reason = $validated['ban_reason'];
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
        $user->ban_reason = null;
        $user->save();

        return $this->success('User has been lifted from ban.');
    }

    public function profile()
    {
        return $this->success(Auth::user(), 'User information');
    }

    public function updateUsername(Request $request)
    {
        $user = Auth::user();
        $user->name = $request->name;
        $user->save();
        return $this->success($user, 'Username updated successfully');
    }

    public function updateProfileImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $uploadPath = public_path('storage/profiles');

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $imageName = time() . '.' . $validated['image']->extension();

        try {
            $validated['image']->move($uploadPath, $imageName);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        $user = Auth::user();

        if ($user->profile_image) {
            $oldImagePath = public_path(parse_url($user->profile_image, PHP_URL_PATH));
            if (File::exists($oldImagePath)) {
                File::delete($oldImagePath);
            }
        }

        $host = $request->getSchemeAndHttpHost();
        $user->profile_image = $host . '/storage/profiles/' . $imageName;
        $user->save();

        return $this->success($user->profile_image, 'Profile image updated successfully');
    }
}
