<?php

namespace App\Http\Requests;

class StorePostRequest extends PostRequest
{
    protected function isUpdate(): bool
    {
        return false;
    }
}
