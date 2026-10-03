<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DesignPictureStorage
{
    /**
     * Upload a design picture to Cloudinary and return the stored public id.
     */
    public function store(UploadedFile $file): string
    {
        $credentials = $this->credentials();
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('The design picture could not be read.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The design picture could not be read.');
        }

        $publicId = 'booking-designs/'.Str::random(15);
        $timestamp = time();
        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'type' => 'authenticated',
        ];

        $response = Http::timeout(30)
            ->attach('file', $contents, 'design.'.($file->guessExtension() ?: 'jpg'))
            ->post($this->uploadUrl($credentials['cloud_name']), [
                ...$params,
                'api_key' => $credentials['api_key'],
                'signature' => $this->sign($params, $credentials['api_secret']),
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message');

            throw new RuntimeException(is_string($message) ? $message : 'Cloudinary upload failed.');
        }

        $payload = $response->json();
        $storedPublicId = is_array($payload) ? ($payload['public_id'] ?? null) : null;
        $format = is_array($payload) ? ($payload['format'] ?? null) : null;

        if (! is_string($storedPublicId) || $storedPublicId === '' || ! is_string($format) || $format === '') {
            throw new RuntimeException('Cloudinary upload did not return a public id.');
        }

        return $storedPublicId.'.'.$format;
    }

    /**
     * @return array{body: string, content_type: string, filename: string}
     */
    public function fetch(string $stored): array
    {
        if (! str_contains($stored, '/')) {
            return $this->fetchLocal($stored);
        }

        return $this->fetchCloudinary($stored);
    }

    /**
     * @return array{body: string, content_type: string, filename: string}
     */
    private function fetchLocal(string $filename): array
    {
        $path = 'booking-designs/'.$filename;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $body = $disk->get($path);
        abort_if($body === null, 404);

        return [
            'body' => $body,
            'content_type' => $this->contentTypeFor($filename),
            'filename' => $this->downloadFilename($filename),
        ];
    }

    /**
     * @return array{body: string, content_type: string, filename: string}
     */
    private function fetchCloudinary(string $stored): array
    {
        $credentials = $this->credentials();
        $format = pathinfo($stored, PATHINFO_EXTENSION);
        $publicId = substr($stored, 0, -strlen($format) - 1);

        if ($format === '' || $publicId === '') {
            abort(404);
        }

        $params = [
            'expires_at' => time() + 120,
            'format' => $format,
            'public_id' => $publicId,
            'timestamp' => time(),
            'type' => 'authenticated',
        ];
        $params['signature'] = $this->sign($params, $credentials['api_secret']);
        $params['api_key'] = $credentials['api_key'];

        $response = Http::timeout(30)->get(
            $this->downloadUrl($credentials['cloud_name']).'?'.http_build_query($params),
        );

        $mime = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));

        if (! $response->successful() || ! str_starts_with($mime, 'image/')) {
            abort(404);
        }

        return [
            'body' => $response->body(),
            'content_type' => $mime,
            'filename' => $this->downloadFilename($stored),
        ];
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function sign(array $params, string $apiSecret): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if ($value === '') {
                continue;
            }

            $pairs[$key] = $key.'='.(string) $value;
        }

        ksort($pairs);

        return hash('sha1', implode('&', $pairs).$apiSecret);
    }

    /**
     * @return array{cloud_name: string, api_key: string, api_secret: string}
     */
    private function credentials(): array
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (! is_string($cloudName) || $cloudName === '' || ! is_string($apiKey) || $apiKey === '' || ! is_string($apiSecret) || $apiSecret === '') {
            throw new RuntimeException('Cloudinary credentials are not configured.');
        }

        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    private function uploadUrl(string $cloudName): string
    {
        return 'https://api.cloudinary.com/v1_1/'.rawurlencode($cloudName).'/image/upload';
    }

    private function downloadUrl(string $cloudName): string
    {
        return 'https://api.cloudinary.com/v1_1/'.rawurlencode($cloudName).'/image/download';
    }

    private function contentTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    private function downloadFilename(string $stored): string
    {
        $filename = basename($stored);

        if (preg_match('/^[A-Za-z0-9._-]+$/', $filename) !== 1) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            return 'design-picture'.($extension !== '' ? '.'.$extension : '');
        }

        return $filename;
    }
}
