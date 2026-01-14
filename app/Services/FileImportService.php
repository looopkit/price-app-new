<?php

namespace App\Services;

use App\Jobs\ImportFileJob;
use App\Models\Account;
use App\Models\File;
use App\Models\FileRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileImportService
{
    /**
     * Import offers from CSV/Excel file
     */
    public function importOffers(Account $account, UploadedFile $uploadedFile, array $mapping): File
    {
        // Store file
        $path = $uploadedFile->store('imports', 'local');
        $name = $uploadedFile->getClientOriginalName();

        // Create File record
        $file = File::create([
            'account_id' => $account->id,
            'name' => $name,
            'path' => $path,
            'type' => 'import',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
        ]);

        Log::info('File created for import', [
            'file_id' => $file->id,
            'account_id' => $account->id,
            'name' => $name,
        ]);

        // Parse file and create rows
        $rowCount = $this->parseFileAndCreateRows($file, $path, $mapping);

        $file->update(['total_rows' => $rowCount]);

        // Dispatch job to process file
        ImportFileJob::dispatch($file->id);

        Log::info('File import job dispatched', [
            'file_id' => $file->id,
            'rows_count' => $rowCount,
        ]);

        return $file;
    }

    /**
     * Parse file and create FileRow records
     */
    private function parseFileAndCreateRows(File $file, string $path, array $mapping): int
    {
        $fullPath = Storage::disk('local')->path($path);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            throw new \Exception('Unsupported file format');
        }

        if ($extension === 'csv') {
            return $this->parseCsv($file, $fullPath, $mapping);
        }

        // For Excel files, you would use PhpSpreadsheet or similar
        throw new \Exception('Excel import not yet implemented');
    }

    /**
     * Parse CSV file
     */
    private function parseCsv(File $file, string $path, array $mapping): int
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \Exception('Cannot open file');
        }

        $rowNumber = 0;
        $headers = null;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNumber++;

            // First row is headers
            if ($rowNumber === 1) {
                $headers = $data;
                continue;
            }

            if (!$headers) {
                continue;
            }

            // Map columns to data
            $rowData = $this->mapRowData($data, $headers, $mapping);

            // Create FileRow
            FileRow::create([
                'file_id' => $file->id,
                'row_number' => $rowNumber,
                'data' => $rowData,
                'status' => 'pending',
            ]);
        }

        fclose($handle);

        return $rowNumber - 1; // Exclude header row
    }

    /**
     * Map CSV row to structured data using mapping
     */
    private function mapRowData(array $row, array $headers, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $field => $columnIndex) {
            if (is_numeric($columnIndex) && isset($row[$columnIndex])) {
                $data[$field] = $row[$columnIndex];
            } elseif (is_string($columnIndex)) {
                // Column name mapping
                $index = array_search($columnIndex, $headers);
                if ($index !== false && isset($row[$index])) {
                    $data[$field] = $row[$index];
                }
            }
        }

        return $data;
    }

    /**
     * Get import status
     */
    public function getStatus(File $file): array
    {
        $failedRows = $file->rows()->byStatus('failed')->get();

        return [
            'file_id' => $file->id,
            'status' => $file->status,
            'total_rows' => $file->total_rows,
            'processed_rows' => $file->processed_rows,
            'progress_percentage' => $file->total_rows > 0 
                ? round(($file->processed_rows / $file->total_rows) * 100, 2)
                : 0,
            'failed_rows_count' => $failedRows->count(),
            'failed_rows' => $failedRows->map(fn($row) => [
                'row_number' => $row->row_number,
                'data' => $row->data,
                'error' => $row->error_message,
            ])->toArray(),
        ];
    }
}