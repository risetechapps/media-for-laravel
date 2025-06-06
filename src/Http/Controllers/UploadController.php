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
                    'preview' => $dataFile->getFullUrlTemporaryUpload(),
                    'collection' => $request->input('collection')  ?? 'uploads',
                ];

                logglyInfo()->withRequest($request)->performedOn($uploadTemporary)
                    ->withTags(['data' => $data])->log("Successful loaded upload");

                return response()->jsonSuccess($data);
            }

            logglyError()->withRequest($request)->withRequest($request)->log("Error uploading the file");

            return response()->jsonGone();
        } catch (Exception $exception) {

            logglyError()->withRequest($request)->withRequest($request)->exception($exception)->log("Error uploading the file");


            return response()->jsonGone();
        }
    }
}
