<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $attempts = RateLimiter::hit($this->attemptsKey(), $this->lockoutSeconds());

            if ($attempts >= $this->maxAttempts()) {
                RateLimiter::clear($this->attemptsKey());
                RateLimiter::hit($this->lockoutKey(), $this->lockoutSeconds());

                $this->throwLockoutValidationException();
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }
    }

    public function clearThrottle(): void
    {
        RateLimiter::clear($this->attemptsKey());
        RateLimiter::clear($this->lockoutKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->lockoutKey(), 1)) {
            return;
        }

        $this->throwLockoutValidationException();
    }

    private function throwLockoutValidationException(): never
    {
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->lockoutKey());
        $this->session()->flash('login_lockout_until', now()->addSeconds($seconds)->timestamp);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function maxAttempts(): int
    {
        return (int) config('security.login.max_attempts', 5);
    }

    private function lockoutSeconds(): int
    {
        return (int) config('security.login.lockout_seconds', 300);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function attemptsKey(): string
    {
        return 'login-attempts:'.$this->throttleKey();
    }

    private function lockoutKey(): string
    {
        return 'login-lockout:'.$this->throttleKey();
    }
}
