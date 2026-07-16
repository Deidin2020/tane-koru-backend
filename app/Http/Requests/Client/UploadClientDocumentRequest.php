<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UploadClientDocumentRequest extends ApiFormRequest
{
    private const MAX_FILE_SIZE_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['passport_id', 'reservation_form', 'sales_contract', 'payment_receipt', 'other'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Please select a document type.',
            'document_type.in' => 'The selected document type is invalid.',
            'file.required' => 'Please select a file before uploading.',
            'file.file' => 'Please upload a valid file.',
            'file.mimes' => 'The file must be a PDF, JPG, JPEG, PNG, or WEBP.',
            'file.max' => 'The file size must not exceed 10 MB.',
        ];
    }
}
