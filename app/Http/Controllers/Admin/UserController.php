<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount('trips');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('full_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        $users = $query->latest()->paginate(25)->withQueryString();

        $totalActiveUsers = User::where('role', '!=', 'banned')->count();
        $tripsThisMonth   = Trip::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        return view('admin.users.index', compact('users', 'totalActiveUsers', 'tripsThisMonth'));
    }

    public function show(User $user)
    {
        $user->load(['trips', 'reviews', 'expenses']);
        return view('admin.users.show', compact('user'));
    }

    public function ban(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot ban yourself.');
        $user->update(['role' => $user->role === 'banned' ? 'traveler' : 'banned']);
        $action = $user->role === 'banned' ? 'banned' : 'unbanned';
        return back()->with('success', "User {$action}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
