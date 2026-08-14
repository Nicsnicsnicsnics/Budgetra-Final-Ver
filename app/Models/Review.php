<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'attraction_id', 'destination', 'rating', 'body', 'status',
        'flag_reason', 'flagged_at', 'flagged_by',
    ];

    protected function casts(): array
    {
        return ['flagged_at' => 'datetime'];
    }

    public function user()       { return $this->belongsTo(User::class); }
    public function attraction() { return $this->belongsTo(Attraction::class); }
    public function flagger()    { return $this->belongsTo(User::class, 'flagged_by'); }
}
