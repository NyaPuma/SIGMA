<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CloseTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_cost' => ['required', 'numeric', 'min:0'],
            'minutes_spent' => ['nullable', 'integer', 'min:0'],
            'report' => ['nullable', 'string', 'max:5000'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actual_cost' => __('common.custo real'),
            'report' => __('common.relatório técnico'),
            'force' => __('common.forçar encerramento'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actual_cost.required' => __('validation.O custo real é obrigatório.'),
            'actual_cost.numeric' => __('validation.O custo real deve ser um valor numérico.'),
            'actual_cost.min' => __('equipment.O custo real não pode ser um valor negativo.'),
            'report.max' => __('common.O relatório técnico não pode exceder 5000 caracteres.'),
            'force.boolean' => __('validation.O campo forçar encerramento deve ser verdadeiro ou falso.'),
        ];
    }
}
