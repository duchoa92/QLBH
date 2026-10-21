<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    public function show(string $filename): Response
    {
        abort_unless(
            preg_match('/^[A-Za-z0-9._-]+$/', $filename) === 1,
            404
        );

        $path = 'products/' . $filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
