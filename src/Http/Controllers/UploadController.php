<?php

namespace RiseTechApps\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RiseTechApps\Media\Models\MediaUploadTemporary;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        try {
            if ($request->hasFile('file')) {

                $file = $request->file('file');
                $uploadTemporary = (new MediaUploadTemporary())->create();
                $dataFile = $uploadTemporary->addMediaFromRequest('file')
                    ->toMediaCollection($request->input('collection') ?? 'uploads')->usingTemporaryUploads();

                $data = [
                    'id' => $dataFile->model_id,
                    'name' => $file->getClientOriginalName(),
                    'type' => $dataFile->mime_type,
                    'size' => $dataFile->size,
                    'preview' => $dataFile->getFullUrl(),
                    'collection' => $request->input('collection')  ?? 'uploads',
                ];

                return response()->json(['success' => true, 'data' => $data]);
            }
            return response()->json(['success' => false], 412);
        } catch (Exception $exception) {
            return response()->json(['success' => false], 412);
        }
    }
}
