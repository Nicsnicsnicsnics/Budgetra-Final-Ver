@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="page-header">
    <h1>My Profile</h1>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2em;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if ($user->profile_photo)
            <div style="margin-bottom:1.5rem;">
                <img src="{{ Storage::url($user->profile_photo) }}"
                     width="80" height="80"
                     style="border-radius:50%;object-fit:cover;"
                     alt="Profile photo">
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="first_name">First Name <span style="color:red">*</span></label>
                <input type="text" id="first_name" name="first_name"
                       class="form-control @error('first_name') is-invalid @enderror"
                       value="{{ old('first_name', $user->first_name) }}" required>
                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name"
                       class="form-control @error('middle_name') is-invalid @enderror"
                       value="{{ old('middle_name', $user->middle_name) }}">
                @error('middle_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="last_name">Last Name <span style="color:red">*</span></label>
                <input type="text" id="last_name" name="last_name"
                       class="form-control @error('last_name') is-invalid @enderror"
                       value="{{ old('last_name', $user->last_name) }}" required>
                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number"
                       class="form-control @error('contact_number') is-invalid @enderror"
                       value="{{ old('contact_number', $user->contact_number) }}">
                @error('contact_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="profile_photo">Profile Photo</label>
                <input type="file" id="profile_photo" name="profile_photo"
                       class="form-control @error('profile_photo') is-invalid @enderror"
                       accept="image/jpeg,image/png,image/jpg,image/webp">
                @error('profile_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>
@endsection
