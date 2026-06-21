<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'data_provider' => ['nullable', 'string', 'max:255'],
            'license_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
