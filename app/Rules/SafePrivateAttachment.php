<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafePrivateAttachment implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('O arquivo enviado não é válido.');

            return;
        }

        $name = strtolower($value->getClientOriginalName());
        $segments = explode('.', $name);
        array_pop($segments);
        $dangerousExtensions = ['bat', 'cmd', 'com', 'exe', 'htm', 'html', 'js', 'phtml', 'phar', 'php', 'sh', 'svg'];

        if (array_intersect($segments, $dangerousExtensions)) {
            $fail('Nomes de arquivo com extensão executável não são permitidos.');

            return;
        }

        $path = $value->getRealPath();
        $sample = is_string($path) ? file_get_contents($path, false, null, 0, 8192) : false;
        if (is_string($sample) && preg_match('/<\?(?:php|=|\s)|<script\b|^#!|^MZ|^\x7fELF/i', $sample) === 1) {
            $fail('O conteúdo do arquivo não corresponde a uma evidência segura.');
        }
    }
}
