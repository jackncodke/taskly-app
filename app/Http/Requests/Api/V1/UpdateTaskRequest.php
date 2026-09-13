<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest as WebUpdateTaskRequest;
use App\TaskStatus;
use Illuminate\Validation\Rule;

/**
 * The API's task update, which is a PATCH rather than the form's full
 * resubmission.
 *
 * Extending the form's request keeps one copy of the field rules, the tag
 * splitting and the "an overdue task can still be edited" exception. The only
 * differences are the two the verb implies: every field becomes `sometimes`,
 * so a client may send just the one it means to change, and `status` is
 * editable here instead of through a separate single-field endpoint.
 */
class UpdateTaskRequest extends WebUpdateTaskRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `sometimes` with `required` together mean "if you send this field, it
     * must hold a value" — an absent field is left alone, while an explicitly
     * blank one is still rejected rather than wiping the stored value.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'due_at' => [
                'sometimes',
                'required',
                'date',
                ...$this->keepsStoredDeadline()
                    ? []
                    : ['after_or_equal:'.StoreTaskRequest::earliestDeadline($this)],
            ],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'attachments' => ['sometimes', 'array', 'max:10'],
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
            ...parent::messages(),
            'status.required' => 'Informe o status da tarefa.',
            'status.enum' => 'Status inválido.',
        ];
    }
}
