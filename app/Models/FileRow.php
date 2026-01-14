<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'row_number',
        'data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
        'row_number' => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function scopeForFile($query, int $fileId)
    {
        return $query->where('file_id', $fileId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function markAsProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}