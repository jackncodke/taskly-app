<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership of the parent task is enforced by TaskPolicy in the
     * controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The same size and type limits the task form applies, restated here
     * because this endpoint accepts nothing else: uploading to an existing
     * task is its own operation in the API, not a field on an update.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
            ],
        ];
    }

    /**
     * Get the validation messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Envie ao menos um arquivo.',
            'attachments.max' => 'Envie no máximo :max arquivos por vez.',
            'attachments.*.max' => 'Cada arquivo deve ter no máximo 10 MB.',
            'attachments.*.mimes' => 'Formato de arquivo não permitido.',
        ];
    }
}
