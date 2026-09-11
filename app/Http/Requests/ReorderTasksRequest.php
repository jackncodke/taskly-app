<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class ReorderTasksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization runs here rather than in the controller because the rule
     * below reads the project's task ids. Checking afterwards would let a
     * stranger tell an empty project from a full one by the error they get
     * back, which is exactly what the policy's "deny as not found" avoids.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->project());

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*' => ['integer'],
        ];
    }

    /**
     * Require the payload to be a rearrangement of this project's tasks.
     *
     * A partial or padded list would silently scramble the order of whatever
     * it left out, so anything but an exact permutation is rejected.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $submitted = array_map(intval(...), (array) $this->input('tasks'));
                sort($submitted);

                $owned = $this->project()
                    ->tasks()
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                if ($submitted !== $owned) {
                    $validator->errors()->add(
                        'tasks',
                        'A lista enviada não corresponde às tarefas do projeto.'
                    );
                }
            },
        ];
    }

    /**
     * The project whose tasks are being reordered.
     */
    protected function project(): Project
    {
        $project = $this->route('project');

        assert($project instanceof Project);

        return $project;
    }
}
