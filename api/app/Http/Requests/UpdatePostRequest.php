<?php

namespace App\Http\Requests;

class UpdatePostRequest extends PostRequest
{
    protected function isUpdate(): bool
    {
        return true;
    }
}
