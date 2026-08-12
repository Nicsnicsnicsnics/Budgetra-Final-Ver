<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    private function filteredQuery(Request $request)
    {
        $query = User::withCount('trips');
        if ($request->filled('search')) {
            $s = '%' . strtolower($request->search) . '%';
            $query->where(fn($q) => $q->whereRaw('LOWER(full_name) LIKE ?', [$s])->orWhereRaw('LOWER(email) LIKE ?', [$s]));
        }
        if ($request->filled('status')) {
            $request->status === 'banned'
                ? $query->where('role', 'banned')
                : $query->where('role', '!=', 'banned');
        }
        return $query;
    }

    public function index(Request $request)
    {
<<<<<<< HEAD
        $active = 'users';
        $users = $this->filteredQuery($request)->latest()->paginate(25)->withQueryString();

        $activeUsers  = User::where('role', '!=', 'banned')->count();
        $bannedUsers  = User::where('role', 'banned')->count();
        $tripsThisMonth = Trip::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.users.index', compact('users', 'active', 'activeUsers', 'bannedUsers', 'tripsThisMonth'));
    }

    public function export(Request $request): StreamedResponse
    {
        $users = $this->filteredQuery($request)->latest()->get();

        $filename = 'users-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($users) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Role', 'Trips', 'Registered']);
            foreach ($users as $user) {
                fputcsv($out, [
                    $user->full_name,
                    $user->email,
                    $user->role,
                    $user->trips_count,
                    $user->created_at->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, $filename, $headers);
=======
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
>>>>>>> 537609b8368acc8725e027fe8e60d1600528fadc
    }

    public function show(User $user)
    {
        $active = 'users';
        $user->load(['trips', 'reviews', 'expenses']);
        return view('admin.users.show', compact('user', 'active'));
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
