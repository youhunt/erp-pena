<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class EnglishUiTextFilter implements FilterInterface
{
    private const SESSION_KEY = 'ui_locale';
    private const SUPPORTED = ['en', 'id'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $requested = strtolower(trim((string) $request->getGet('lang')));
        if (in_array($requested, self::SUPPORTED, true)) {
            session()->set(self::SESSION_KEY, $requested);
            service('language')->setLocale($requested);
            return null;
        }

        $locale = $this->locale();
        service('language')->setLocale($locale);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $contentType = strtolower((string) $response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        $locale = $this->locale();
        $messages = $this->messages($locale);
        $replacements = $messages['replacements'] ?? [];
        if (is_array($replacements) && $replacements !== []) {
            $body = strtr($body, $replacements);
        }

        $body = preg_replace('/<html\s+lang="[^"]*"/i', '<html lang="' . $locale . '"', $body) ?? $body;
        $response->setBody($body);

        return null;
    }

    private function locale(): string
    {
        $locale = strtolower(trim((string) session(self::SESSION_KEY)));
        return in_array($locale, self::SUPPORTED, true) ? $locale : 'en';
    }

    private function messages(string $locale): array
    {
        $file = APPPATH . 'Language/' . $locale . '/Ui.php';
        if (! is_file($file)) {
            $file = APPPATH . 'Language/en/Ui.php';
        }

        $messages = require $file;
        return is_array($messages) ? $messages : [];
    }
}
