<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsIssue extends Model
{
    protected $fillable = ['shipment_id', 'company_name', 'hub_id', 'reported_by', 'assigned_to', 'kind', 'description', 'status', 'resolution', 'resolved_at','recovery_key','recovery','rts_shipment_id','rts','investigation'];

    protected $casts = ['resolved_at' => 'datetime','recovery'=>'array','rts'=>'array','investigation'=>'array'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
