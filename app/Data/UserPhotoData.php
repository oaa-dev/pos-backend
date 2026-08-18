<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UserPhotoData extends Data
{
    public function __construct(public UploadedFile $photo) {}
}
