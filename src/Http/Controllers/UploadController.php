<?php

namespace RiseTechApps\Media\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RiseTechApps\Media\Http\Resources\TemporaryUploadResource;
use RiseTechApps\Media\Models\MediaUploadTemporary;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => ['required', 'file', 'max:' . config('media.upload.max_size', 51200)],
            'collection' => ['nullable', 'string', 'max:255'],
        ]);

        try {

            if ($request->hasFile('file')) {

                $file = $request->file('file');
                $collection = $request->input('collection') ?? 'uploads';
                $uploadTemporary = new MediaUploadTemporary()->create();
                $dataFile = $uploadTemporary->addMediaFromRequest('file')
                    ->toMediaCollection($collection)->usingTemporaryUploads();

                $data = (new TemporaryUploadResource($dataFile, $file->getClientOriginalName(), $collection))
                    ->resolve($request);

                logglyInfo()->withRequest($request)->performedOn($uploadTemporary)
                    ->withTags(['data' => $data])->log("Successful loaded upload");

                return response()->jsonSuccess($data);
            }

            logglyWarning()->withRequest($request)->log("Error uploading the file");

            return response()->jsonGone();
        } catch (Exception $exception) {

            logglyError()->withRequest($request)->exception($exception)->log("Error uploading the file");


            return response()->jsonGone();
        }
    }
}
