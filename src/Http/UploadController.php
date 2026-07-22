<?php

namespace RiseTechApps\Media\Http;

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
        // Fora do try: a exceção de validação deve subir e virar 422 com os
        // erros de campo, em vez de ser capturada como falha de upload.
        $request->validate([
            'file' => ['required', 'file', 'max:' . config('media.upload.max_size', 51200)],
            'collection' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $file = $request->file('file');
            $collection = $request->input('collection') ?? 'uploads';

            $uploadTemporary = MediaUploadTemporary::query()->create();

            $media = $uploadTemporary->addMediaFromRequest('file')
                ->toMediaCollection($collection);

            $data = (new TemporaryUploadResource($media, $file->getClientOriginalName()))
                ->resolve($request);

            logglyInfo()->withRequest($request)->performedOn($uploadTemporary)
                ->withTags(['data' => $data])->log("Successful loaded upload");

            return response()->jsonSuccess($data);
        } catch (Exception $exception) {
            logglyError()->withRequest($request)->exception($exception)->log("Error uploading the file");

            return response()->jsonGone();
        }
    }
}
