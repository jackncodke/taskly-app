<?php

namespace App\Http\Requests;

use App\TaskStatus;
use Illuminate\Validation\Rule;

/**
 * Moving a task on the board is one status change plus one reordering, so this
 * extends the reorder request rather than restating it: the authorization and
 * the "must be an exact permutation" rule are the same, and inheriting them
 * keeps the two endpoints from drifting apart.
 */
class MoveTaskRequest extends ReorderTasksRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::enum(TaskStatus::class)],
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
            'status.required' => 'Informe o status da tarefa.',
            'status.enum' => 'Status inválido.',
        ];
    }
}
