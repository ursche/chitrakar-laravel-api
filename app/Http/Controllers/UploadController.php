<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(10 * 1024),   // kilobytes — 10 MB
            ],
        ]);

        $uploadedFile = $request->file('file');

        $path = $uploadedFile->store(
            'uploads/' . $request->user()->id,
            'public'
        );

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url'        => $url,
            'key'        => $path,
            'size_bytes' => $uploadedFile->getSize(),
            'mime_type'  => $uploadedFile->getMimeType(),
        ], 201);
    }
}
