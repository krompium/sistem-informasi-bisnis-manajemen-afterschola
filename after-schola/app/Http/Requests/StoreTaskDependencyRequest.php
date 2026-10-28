<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskDependency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTaskDependencyRequest extends FormRequest
{
    // Mengatur ketergantungan = mengubah task: Management read-only, non-anggota 404.
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'depends_on_id' => [
                'bail', 'required', 'integer',
                // Prasyarat harus task lain di proyek yang sama (ini juga menutup akses ke task team lain).
                Rule::exists('tasks', 'id')->where('project_id', $task->project_id),
                Rule::notIn([$task->id]),
                Rule::unique('task_dependencies', 'depends_on_id')->where('task_id', $task->id),
                function (string $attribute, mixed $value, Closure $fail) use ($task) {
                    if ($this->createsCycle($task, (int) $value)) {
                        $fail('Ketergantungan ini akan membentuk lingkaran antar-task.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'depends_on_id.exists' => 'Task prasyarat harus berada di proyek yang sama.',
            'depends_on_id.not_in' => 'Task tidak boleh bergantung pada dirinya sendiri.',
            'depends_on_id.unique' => 'Ketergantungan ini sudah ada.',
        ];
    }

    /** Task $task bergantung pada $dependsOnId akan melingkar bila $dependsOnId (lewat rantai prasyarat) sudah bergantung pada $task. */
    private function createsCycle(Task $task, int $dependsOnId): bool
    {
        $edges = TaskDependency::query()
            ->whereIn('task_id', Task::where('project_id', $task->project_id)->select('id'))
            ->get(['task_id', 'depends_on_id'])
            ->groupBy('task_id');

        $stack = [$dependsOnId];
        $seen = [];

        while ($stack) {
            $current = array_pop($stack);

            if ($current === $task->id) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            foreach ($edges->get($current, []) as $edge) {
                $stack[] = (int) $edge->depends_on_id;
            }
        }

        return false;
    }
}
