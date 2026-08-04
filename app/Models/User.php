<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'first_name', 'middle_name', 'last_name', 'contact_number',
        'email', 'password', 'phone', 'country',
        'currency_code', 'currency_symbol', 'role', 'profile_photo', 'theme',
        'default_buffer_pct', 'notify_budget_alerts', 'notify_trip_reminders',
        'notify_itinerary_reminders', 'ocr_auto_categorize',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'                    => 'hashed',
            'notify_budget_alerts'        => 'boolean',
            'notify_trip_reminders'       => 'boolean',
            'notify_itinerary_reminders'  => 'boolean',
            'ocr_auto_categorize'         => 'boolean',
            'password_changed_at'         => 'datetime',
        ];
    }

    public function trips()       { return $this->hasMany(Trip::class); }
    public function expenses()    { return $this->hasMany(Expense::class); }
    public function savingsGoals(){ return $this->hasMany(SavingsGoal::class); }
    public function notifications(){ return $this->hasMany(Notification::class); }
    public function reviews()     { return $this->hasMany(Review::class); }
    public function ocrLogs()     { return $this->hasMany(OcrLog::class); }
    public function userProfile() { return $this->hasOne(UserProfile::class); }
}
