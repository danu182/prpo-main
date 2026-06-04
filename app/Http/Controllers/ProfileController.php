<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    // public function update(ProfileUpdateRequest $request): RedirectResponse
    // {
    //     $request->user()->fill($request->validated());

    //     if ($request->user()->isDirty('email')) {
    //         $request->user()->email_verified_at = null;
    //     }

    //     $request->user()->save();

    //     return Redirect::route('profile.edit')->with('status', 'profile-updated');
    // }

    public function update(Request $request)
    {
        $user = auth()->user();

        // 1. Validasi
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'avatar'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
            // 'signature' => 'nullable|image|mimes:png|max:2048',          // Max 2MB
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password'  => 'nullable|min:6|confirmed',
        ]);

        // 2. Update Data Diri
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = \Hash::make($request->password);
        }

        // 3. Upload Avatar (Folder: public/users/{id}/avatar)
        if ($request->hasFile('avatar')) {
            // Hapus file lama
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }
            // Simpan file baru di folder spesifik User ID
            $path = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');
            $user->avatar = $path;
        }

        // 4. Upload Signature (Folder: public/users/{id}/signature)
        if ($request->hasFile('signature')) {
            // Hapus file lama
            if ($user->signature && \Storage::disk('public')->exists($user->signature)) {
                \Storage::disk('public')->delete($user->signature);
            }
            // Simpan file baru di folder spesifik User ID
            $path = $request->file('signature')->store("users/{$user->id}/signature", 'public');
            $user->signature = $path;
        }

        $user->save();

        return back()->with('success', 'Profil berhasil diperbarui!');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
