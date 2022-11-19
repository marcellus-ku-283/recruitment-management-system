<?php

namespace App\Services;

use App\Exceptions\CustomException;
use App\Models\User;
use App\Notifications\ForgetPassword;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function login($inputs = [])
    {
        $user = $this->user
                    ->whereEmail($inputs['email'])->first();

        if (empty($user) && Hash::check($user->password, $inputs['password'])) {
            throw new CustomException(__('auth.failed'), 401);
        }

        return [
            'user' => $user,
            'token' => $user->createToken(config('app.name'))->plainTextToken
        ];
    }

    public function forgetPassword($inputs = [])
    {
        $user = $this->user->whereEmail($inputs['email'])->firstOrFail();

        $user->createOtp();

        $user->notify(new ForgetPassword($user));

        return [
            'message' => __('messages.forgotPassword') 
        ];
    }

    public function resetPassword($inputs = [])
    {
        $user = $this->user->whereEmail($inputs['email'])->firstOrFail();

        if (empty($user->otp)) {
            throw new CustomException(__('messages.invalidCode'), 400);
        }

        if ($user->otp != $inputs['code']) {
            throw new CustomException(__('messages.invalidCode'), 400);
        }

        if (strtotime($user->otp_expired_at) < strtotime(Carbon::now())) {
            throw new CustomException(__('messages.expiredCode'), 400);
        }

        $user->update([
            'otp' => null,
            'otp_expired_at' => null,
            'password' => Hash::make($inputs['password'])
        ]);

        return [
            'message' => __('messages.passwordUpdated')
        ];
    }
}