<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Allow-listed upload targets — never trust an arbitrary class name from
     * the client. Each maps to the Eloquent model and which collections on
     * it are valid to upload into.
     */
    private const MODELS = [
        'section' => ['class' => Section::class, 'collections' => ['content', 'attachments']],
        'post' => ['class' => Post::class, 'collections' => ['image', 'attachments']],
    ];

    public function store(Request $request): JsonResponse
    {
        $modelType = $request->input('model_type');
        $collections = self::MODELS[$modelType]['collections'] ?? ['content'];
        $collection = $request->input('collection', $collections[0]);

        $validated = $request->validate([
            'model_type' => ['required', Rule::in(array_keys(self::MODELS))],
            'model_id' => ['required', 'integer'],
            'collection' => ['sometimes', Rule::in($collections)],
            // 'attachments' (inline Tiptap uploads) also allows PDFs, since
            // an editor can attach a document to download, not just embed a
            // picture. Every other collection ('content', 'image', ...)
            // stays image-only, one file.
            'file' => $collection === 'attachments'
                ? ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240']
                : ['required', 'file', 'image', 'max:10240'],
        ]);

        /** @var HasMedia $model */
        $model = self::MODELS[$modelType]['class']::findOrFail($validated['model_id']);

        $media = $model->addMediaFromRequest('file')->toMediaCollection($collection);

        return response()->json([
            'url' => route('media.show', ['media' => $media->id, 'filename' => $media->file_name]),
            'filename' => $media->file_name,
            'mimeType' => $media->mime_type,
        ]);
    }

    /**
     * Streams a media file from the tenant's own storage.
     *
     * Not served via the static public/storage symlink: that symlink is
     * fixed at one path, but FilesystemTenancyBootstrapper suffixes the
     * 'public' disk per tenant, so each tenant's uploads physically live in
     * their own storage/tenant<id>/app/public directory. Going through a
     * route means the tenant is already resolved (InitializeTenancyByDomain
     * ran first) and Media::getPath() naturally points at the right file —
     * no per-tenant symlink juggling needed.
     */
    public function show(Media $media, string $filename): BinaryFileResponse
    {
        if ($media->file_name !== $filename) {
            abort(404);
        }

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
