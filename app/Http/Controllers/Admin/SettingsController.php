<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.index', ['user' => auth()->user()]);
    }
}
