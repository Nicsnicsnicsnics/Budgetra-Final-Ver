@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div style="max-width:640px;margin:0 auto;">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <div style="width:44px;height:44px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa-solid fa-user" style="color:#fff;font-size:18px;"></i>
        </div>
        <div>
            <h1 style="margin:0;">My Profile</h1>
            <p class="text-muted" style="margin:2px 0 0;font-size:13px;">Update your personal details and photo.</p>
        </div>
    </div>

    @if (session('success'))
    <div style="display:flex;align-items:center;gap:8px;color:#16A34A;font-size:13px;font-weight:600;margin-bottom:16px;">
        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
    </div>
    @endif

    @if ($errors->any())
    <div style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.3);border-radius:12px;padding:14px 16px;margin-bottom:16px;">
        <ul style="margin:0;padding-left:1.1em;color:#DC2626;font-size:13px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;box-shadow:0 4px 16px rgba(45,27,20,0.05);overflow:hidden;">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" x-data="{ preview: null, filename: '' }">
            @csrf
            @method('PUT')

            <div style="padding:28px 28px 8px;display:flex;align-items:center;gap:18px;">
                <div style="position:relative;flex-shrink:0;">
                    <div style="width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--primary-light);display:flex;align-items:center;justify-content:center;border:2px solid var(--border);">
                        @if ($user->profile_photo)
                        <img id="avatarPreview" src="{{ Storage::url($user->profile_photo) }}" style="width:100%;height:100%;object-fit:cover;" alt="Profile photo">
                        @else
                        <img id="avatarPreview" src="" style="width:100%;height:100%;object-fit:cover;display:none;" alt="Profile photo">
                        <span id="avatarInitial" style="font-size:26px;font-weight:800;color:var(--primary);">
                            {{ mb_substr($user->first_name ?: $user->full_name ?? 'U', 0, 1) }}
                        </span>
                        @endif
                    </div>
                    <label for="profile_photo" style="position:absolute;bottom:-2px;right:-2px;width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg-white);">
                        <i class="fa-solid fa-camera" style="font-size:10px;"></i>
                    </label>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $user->full_name }}</div>
                    <div style="font-size:12.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                </div>
            </div>

            <div style="padding:20px 28px 28px;">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label for="first_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                            First Name <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               value="{{ old('first_name', $user->first_name) }}"
                               style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('first_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                        @error('first_name') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="middle_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="{{ old('middle_name', $user->middle_name) }}"
                               style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('middle_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                        @error('middle_name') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label for="last_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                        Last Name <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" required
                           value="{{ old('last_name', $user->last_name) }}"
                           style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('last_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                    @error('last_name') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:20px;">
                    <label for="contact_number" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Contact Number</label>
                    <div style="display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid {{ $errors->has('contact_number') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;">
                        <i class="fa-solid fa-phone" style="color:var(--muted);font-size:12px;"></i>
                        <input type="text" id="contact_number" name="contact_number"
                               value="{{ old('contact_number', $user->contact_number) }}"
                               placeholder="e.g. 0917 123 4567"
                               style="flex:1;border:none;outline:none;background:transparent;font-size:13px;font-weight:600;color:var(--dark);">
                    </div>
                    @error('contact_number') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:24px;">
                    <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Profile Photo</label>
                    <label for="profile_photo" class="profile-photo-dropzone"
                           style="display:flex;align-items:center;gap:10px;width:100%;box-sizing:border-box;border:1.5px dashed var(--border);border-radius:14px;padding:14px 16px;cursor:pointer;transition:border-color .15s ease,background .15s ease;">
                        <div style="width:32px;height:32px;border-radius:9px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-cloud-arrow-up" style="color:var(--primary);font-size:13px;"></i>
                        </div>
                        <div style="min-width:0;">
                            <div style="font-size:12px;font-weight:700;color:var(--dark);">
                                <span style="color:var(--primary);">Click to upload</span> a new photo
                            </div>
                            <div id="fileNameLabel" style="font-size:10.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">PNG or JPG, up to 5MB</div>
                        </div>
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;"
                           onchange="
                               var f = this.files[0];
                               document.getElementById('fileNameLabel').textContent = f ? f.name : 'PNG or JPG, up to 5MB';
                               if (f) {
                                   var url = URL.createObjectURL(f);
                                   var img = document.getElementById('avatarPreview');
                                   var initial = document.getElementById('avatarInitial');
                                   img.src = url; img.style.display = 'block';
                                   if (initial) initial.style.display = 'none';
                               }
                           ">
                    @error('profile_photo') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <button type="submit"
                        style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:13px 28px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:background .18s;"
                        onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                    <i class="fa-solid fa-check" style="font-size:11px;"></i> Save Changes
                </button>

            </div>
        </form>
    </div>
</div>

<style>
.profile-photo-dropzone:hover { border-color: var(--primary); background: var(--primary-light); }
</style>
@endsection
