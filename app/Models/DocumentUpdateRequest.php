<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentUpdateRequest extends Model
{
    protected $fillable = [
        'user_id', 'id_type_id', 'id_file', 'business_permit_file',
        'status', 'reviewed_by', 'reviewed_at', 'note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user()       { return $this->belongsTo(User::class); }
    public function reviewer()   { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function idType()     { return $this->belongsTo(IdType::class, 'id_type_id'); }
}
