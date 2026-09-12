<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is enforced by TaskPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Turn the comma separated tag field into a clean list.
     *
     * The form posts tags as one text input because that is how people type
     * them; splitting here keeps the rules below working on a real array and
     * stops blank entries from a trailing comma reaching the database.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('tags') || is_array($this->input('tags'))) {
            return;
        }

        $this->merge([
            'tags' => Str::of((string) $this->input('tags'))
                ->explode(',')
                ->map(fn (string $tag): string => trim($tag))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Whether the request leaves the deadline exactly as the task already
     * has it.
     *
     * A deadline slips into the past just by time passing, so applying the
     * "not in the past" rule to every update would lock an overdue task out of
     * every other edit — its title could not be corrected without also picking
     * a new deadline. The rule is about *setting* a deadline in the past, so
     * keeping the stored one is always allowed.
     */
    protected function keepsStoredDeadline(): bool
    {
        $task = $this->route('task');

        assert($task instanceof Task);

        if ($task->due_at === null) {
            return false;
        }

        // Parsing is what the `date` rule is still there to police; a value it
        // chokes on simply is not the stored one.
        $submitted = rescue(
            fn (): Carbon => Carbon::parse($this->input('due_at')),
            null,
            report: false
        );

        return $submitted?->equalTo($task->due_at) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'due_at' => [
                'required',
                'date',
                ...$this->keepsStoredDeadline()
                    ? []
                    : ['after_or_equal:'.StoreTaskRequest::earliestDeadline()],
            ],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'attachments' => ['array', 'max:10'],
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
            'title.required' => 'Informe o título da tarefa.',
            'title.max' => 'O título deve ter no máximo :max caracteres.',
            'short_description.required' => 'Informe a descrição curta da tarefa.',
            'short_description.max' => 'A descrição curta deve ter no máximo :max caracteres.',
            'description.required' => 'Informe a descrição completa da tarefa.',
            'description.max' => 'A descrição completa deve ter no máximo :max caracteres.',
            'due_at.required' => 'Informe o prazo da tarefa.',
            'due_at.date' => 'Informe um prazo válido.',
            'due_at.after_or_equal' => 'O prazo não pode ser anterior à data e hora atuais.',
            'tags.max' => 'Use no máximo :max tags.',
            'tags.*.max' => 'Cada tag deve ter no máximo :max caracteres.',
            'attachments.max' => 'Envie no máximo :max arquivos por vez.',
            'attachments.*.max' => 'Cada arquivo deve ter no máximo 10 MB.',
            'attachments.*.mimes' => 'Formato de arquivo não permitido.',
        ];
    }
}
