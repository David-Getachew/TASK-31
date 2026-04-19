<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAppointmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'owner_user_id'   => ['required', 'integer', 'exists:users,id'],
            'resource_type'   => ['required', 'string', 'in:facility,registrar_meeting,generic'],
            'resource_ref'    => ['nullable', 'string', 'max:255'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end'   => ['required', 'date', 'after:scheduled_start'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }
}
