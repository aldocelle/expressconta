<?php

namespace App\Http\Requests;

use App\Rules\Rut;
use App\Services\StudyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(StudyContext::class)->admin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rut')) {
            $this->merge(['rut' => Rut::normalize($this->input('rut'))]);
        }
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:180', 'trade_name' => 'nullable|string|max:180', 'rut' => ['required', new Rut, Rule::unique('companies')->where('study_id', app(StudyContext::class)->studyId)->ignore($this->route('company'))], 'business' => 'nullable|string|max:255', 'address' => 'nullable|string|max:255', 'contact_name' => 'nullable|string|max:180', 'contact_email' => 'nullable|email|max:180', 'contact_phone' => 'nullable|string|max:40', 'service' => 'required|string|max:180', 'monthly_fee' => 'required|integer|min:0|max:1000000000', 'starts_on' => 'nullable|date_format:Y-m-d', 'responsible_id' => 'nullable|integer'];
    }
}
