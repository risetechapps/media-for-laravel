<?php

namespace RiseTechApps\Media\Features\Uploads;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class DownloadImageUrlService
{
    protected static array $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
    ];

    public static function get(string $url): ?string
    {
        try {
            if (!Str::isUrl($url)) {
                return null;
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'follow_location' => 1,
                    'max_redirects' => 3,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $imageContent = @file_get_contents($url, false, $context);

            if ($imageContent === false) {
                return null;
            }

            $imageInfo = getimagesizefromstring($imageContent);

            if ($imageInfo === false) {
                return null;
            }

            $mimeType = $imageInfo['mime'];
            $extension = self::$extensions[$mimeType] ?? null;

            if (!$extension) {
                return null;
            }

            if (!File::exists(storage_path('app/temp/'))) {
                File::makeDirectory(storage_path('app/temp/'));
            }

            $imageName = Str::uuid() . '.' . $extension;
            $imagePath = storage_path('app/temp/' . $imageName);

            File::put($imagePath, $imageContent);

            return $imagePath;

        } catch (\Exception $e) {
            logglyError()->withProperties(['url' => $url])->withTags(['action' => 'get'])->log("Error downloading media");
            return null;
        }
    }
}
