<?php

namespace App\Http\Controllers;

use App\Models\StudyMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }
        $r->session()->regenerate();
        $m = StudyMembership::where('user_id', $r->user()->id)->first();
        if (! $m) {
            Auth::guard('web')->logout();
            abort(403, 'No tienes un estudio asignado.');
        }
        $r->session()->put('study_id', $m->study_id);

        return $this->me($r);
    }

    public function me(Request $r)
    {
        $id = $r->session()->get('study_id');

        return response()->json(['user' => $r->user(), 'studies' => $r->user()->studies()->get(), 'study_id' => $id, 'role' => StudyMembership::where('user_id', $r->user()->id)->where('study_id', $id)->value('role')]);
    }

    public function switchStudy(Request $r)
    {
        $d = $r->validate(['study_id' => 'required|integer']);
        abort_unless($r->user()->studies()->whereKey($d['study_id'])->exists(), 403);
        $r->session()->put('study_id', $d['study_id']);

        return $this->me($r);
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return ['message' => 'Si el correo está registrado, recibirás instrucciones para recuperar el acceso.'];
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:12|confirmed']);
        $status = Password::reset($d, function (User $u, string $p) {
            $u->forceFill(['password' => Hash::make($p), 'remember_token' => Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o expiró.']);
        }

return ['message' => 'Contraseña actualizada.'];
    }
}
