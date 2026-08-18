<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class StoreLogoData extends Data
{
    public function __construct(public UploadedFile $logo) {}
}
