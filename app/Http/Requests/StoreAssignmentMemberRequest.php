<?php

namespace App\Http\Requests;

use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->assignment());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('role_id', Role::query()->where('slug', RoleSlug::AuditorSpi)->value('id'))),
                Rule::unique('assignment_members', 'user_id')->where('assignment_id', $this->assignment()->id),
            ],
            'can_access_confidential' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih auditor yang akan ditunjuk.',
            'user_id.exists' => 'Hanya Auditor SPI yang aktif yang bisa ditunjuk.',
            'user_id.unique' => 'Auditor ini sudah ditunjuk di penugasan ini.',
        ];
    }

    public function assignment(): Assignment
    {
        return $this->route('assignment');
    }
}
