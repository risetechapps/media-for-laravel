<?php

namespace RiseTechApps\Media\Traits\HasPhotoProfile;

use Illuminate\Database\Eloquent\Relations\HasOne;
use RiseTechApps\Media\Models\Media;

trait HasPhotoProfile
{
    public function getPhotoProfileUrl(): ?string
    {
        try {
            $photo = $this->getMedia('profile')->first();

            if (is_null($photo)) {
                return null;
            }

            return $photo->getFullUrl();
        } catch (\Exception $exception) {

            logglyError()->performedOn($this)->exception($exception)->log("Error loading profile photo URL");

            return null;
        }
    }

    public function getPhotoProfile(): ?Media
    {
        try {
            $photo = $this->getMedia('profile')->first();

            if (is_null($photo)) {
                return null;
            }

            return $photo;
        } catch (\Exception $exception) {

            logglyError()->performedOn($this)->exception($exception)->log("Error loading profile photo URL");
            return null;
        }
    }

    public function profilePhoto(): HasOne
    {
        return $this->hasOne(Media::class, 'model_id')->where('collection_name', 'profile');
    }
}
