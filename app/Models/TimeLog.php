<?php

namespace App\Models;

use Database\Factories\TimeLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TimeLog extends Model
{
    /** @use HasFactory<TimeLogFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'description',
        'hours',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'logged_at' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasOne<InvoiceItem, $this> */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    public function isBilled(): bool
    {
        return $this->invoiceItem()->exists();
    }
}
