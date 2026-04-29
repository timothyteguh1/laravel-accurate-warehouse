<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAudit extends Model
{
    protected $fillable = [
        'delivery_id',

        // POD
        'pod_status',
        'pod_catatan',

        // Retur
        'ada_retur',
        'retur_persen',
        'retur_alasan',
        'retur_catatan',

        // Uang Jalan
        'uang_diberikan',
        'biaya_bbm',
        'biaya_tol',
        'biaya_kuli',
        'biaya_lain',
        'catatan_biaya',

        // Accurate
        'accurate_action',
        'accurate_result',

        // Status
        'status',
        'audited_by',
        'approved_at',
    ];

    protected $casts = [
        'ada_retur'       => 'boolean',
        'retur_persen'    => 'decimal:2',
        'uang_diberikan'  => 'decimal:2',
        'biaya_bbm'       => 'decimal:2',
        'biaya_tol'       => 'decimal:2',
        'biaya_kuli'      => 'decimal:2',
        'biaya_lain'      => 'decimal:2',
        'accurate_result' => 'array',
        'approved_at'     => 'datetime',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    // ── Accessor: Sisa Uang Jalan ────────────────────────────────────────────
    public function getSisaUangAttribute(): float
    {
        $totalBiaya = $this->biaya_bbm + $this->biaya_tol + $this->biaya_kuli + $this->biaya_lain;
        return (float) $this->uang_diberikan - $totalBiaya;
    }

    // ── Accessor: Total Biaya ────────────────────────────────────────────────
    public function getTotalBiayaAttribute(): float
    {
        return (float) ($this->biaya_bbm + $this->biaya_tol + $this->biaya_kuli + $this->biaya_lain);
    }

    // ── Scope: Hanya yang sudah Approved ────────────────────────────────────
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}