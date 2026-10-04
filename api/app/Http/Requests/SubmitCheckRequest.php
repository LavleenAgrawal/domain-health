<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\DomainNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['tool' => ['required', Rule::in(['blacklist', 'provider'])], 'input' => ['nullable', 'string', 'max:1024'], 'selector' => ['nullable', 'string', 'max:63'], 'show_all_records' => ['nullable', 'boolean'], 'file' => ['nullable', 'file', 'mimes:csv,txt,text/plain', 'max:'.config('domain_health.uploads.max_kb')]];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->input('input') && ! $this->file('file')) {
                $validator->errors()->add('input', 'Enter a domain/email or upload a file.');
            }
            if ($this->input('input') && ! $this->file('file')) {
                $normalizer = app(DomainNormalizer::class);
                try {
                    $normalizer->normalize((string) $this->input('input'));
                } catch (\InvalidArgumentException $exception) {
                    $validator->errors()->add('input', $exception->getMessage());
                }
                try {
                    $normalizer->selector($this->input('selector'));
                } catch (\InvalidArgumentException $exception) {
                    $validator->errors()->add('selector', $exception->getMessage());
                }
            }
        }];
    }
}
