<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reporting\Services\OrganizationReportSettingsPayloadValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizationReportSettingsRequest extends FormRequest
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
        return app(OrganizationReportSettingsPayloadValidator::class)->rules();
    }

    public function withValidator(Validator $validator): void
    {
        app(OrganizationReportSettingsPayloadValidator::class)->after($validator, $this->all());
    }
}
