<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\File;
use App\Models\FileRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        private int $fileId
    ) {}

    public function handle(): void
    {
        $file = File::find($this->fileId);

        if (!$file) {
            Log::warning('File not found', ['file_id' => $this->fileId]);
            return;
        }

        $account = $file->account;

        if (!$account || !$account->isActive()) {
            Log::warning('Account not found or inactive', [
                'file_id' => $this->fileId,
                'account_id' => $file->account_id,
            ]);
            return;
        }

        try {
            $file->markAsProcessing();

            $this->processFile($file);

            $file->markAsCompleted();

            Log::info('File import completed', [
                'file_id' => $file->id,
                'processed_rows' => $file->processed_rows,
                'total_rows' => $file->total_rows,
            ]);
        } catch (\Exception $e) {
            $file->markAsFailed($e->getMessage());

            Log::error('File import failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function processFile(File $file): void
    {
        $rows = $file->rows()->byStatus('pending')->get();

        foreach ($rows as $row) {
            ProcessFileRowJob::dispatch($row->id);
        }
    }
}