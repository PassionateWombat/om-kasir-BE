<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponseTrait;
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed',  400, $validator->errors());
        }

        $user = new User();
        $user->name = request()->name;
        $user->email = request()->email;
        $user->password = bcrypt(request()->password);
        $user->assignRole(['user']);
        $user->save();
        return $this->success($user, 'User registered successfully');
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->error('Unauthorized', 401);
        }
        $claims = [
            'scope' => implode(' ', $user->roles()->pluck('name')->toArray()),
            'userId' => $user->id,
            'email' => $user->email,
        ];
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->claims([
            'scope' => implode(' ', $user->roles()->pluck('name')->toArray()),
            'userId' => $user->id,
            'email' => $user->email,
        ])->setTTL(60)->login($user);
        // $token = JWTAuth::setTTL(1)->claims($claims)->login($user);

        // $token = Auth::claims(['foo' => 'bar'])->attempt($credentials);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        // Check if the user is banned
        if ($user->isBanned()) {
            Auth::logout();
            return $this->error('Your account is banned until ' . $user->banned_at->toDateTimeString(), 403);
        }

        return $this->success($this->respondWithToken($token)->original, 'Login successful');
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return $this->success(Auth::user(), 'User information');
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        Auth::logout();
        return $this->success(null, 'Successfully logged out');
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        Auth::factory()->setTTL(60);
        $token = Auth::refresh();
        return $this->success($this->respondWithToken($token)->original, 'Refresh token successful');
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ]);
    }
}
