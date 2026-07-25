<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'full_name'  => trim($validated['first_name'].' '.$validated['last_name']),
            'email'      => $validated['email'],
            'password'   => $validated['password'],
            'role'       => 'traveler',
        ]);

        return redirect()->route('login');
    }
}
