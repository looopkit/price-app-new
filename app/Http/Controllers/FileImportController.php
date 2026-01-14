<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\File;
use App\Services\FileImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileImportController extends Controller
{
    public function __construct(
        private FileImportService $fileImportService
    ) {}

    /**
     * Upload and import offers from file
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'mapping' => 'required|array',
            'mapping.supplier_external_id' => 'required',
            'mapping.product_external_id' => 'required',
            'mapping.price' => 'required',
            'mapping.stock' => 'sometimes',
            'mapping.priority' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::find($request->account_id);
            $uploadedFile = $request->file('file');
            $mapping = $request->mapping;

            $file = $this->fileImportService->importOffers($account, $uploadedFile, $mapping);

            return response()->json([
                'file_id' => $file->id,
                'name' => $file->name,
                'status' => $file->status,
                'total_rows' => $file->total_rows,
                'message' => 'Import started',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Import failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import status
     */
    public function status(int $fileId): JsonResponse
    {
        $file = File::find($fileId);

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $status = $this->fileImportService->getStatus($file);

        return response()->json($status);
    }

    /**
     * List imports for account
     */
    public function index(int $accountId): JsonResponse
    {
        $account = Account::find($accountId);

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $files = $account->files()
            ->where('type', 'import')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'account_id' => $accountId,
            'imports' => $files->map(fn($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'status' => $file->status,
                'total_rows' => $file->total_rows,
                'processed_rows' => $file->processed_rows,
                'progress' => $file->total_rows > 0 
                    ? round(($file->processed_rows / $file->total_rows) * 100, 2)
                    : 0,
                'created_at' => $file->created_at,
                'updated_at' => $file->updated_at,
            ]),
        ]);
    }
}