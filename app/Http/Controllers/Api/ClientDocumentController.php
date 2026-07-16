<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UploadClientDocumentRequest;
use App\Http\Resources\ClientDocumentResource;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientDocumentController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Client $client): JsonResponse
    {
        return response()->json([
            'data' => ClientDocumentResource::collection(
                $client->documents()->latest()->get()
            )->resolve(),
        ]);
    }

    public function store(UploadClientDocumentRequest $request, Client $client): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = 'client-documents/'.$client->id.'/'.time().'-'.$safeName;

        $document = DB::transaction(function () use ($request, $client, $user, $file, $path): ClientDocument {
            Storage::disk('local')->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

            $document = ClientDocument::query()->create([
                'client_id' => $client->id,
                'document_type' => $request->string('document_type')->toString(),
                'file_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            $type = $document->document_type === 'reservation_form' ? 'reservation_uploaded' : 'document_uploaded';
            $label = Str::replace('_', ' ', $document->document_type);
            $this->activityLogger->log(
                $client,
                $type,
                $user,
                "Uploaded {$document->file_name} ({$label})"
            );

            return $document;
        });

        return response()->json(new ClientDocumentResource($document), 201);
    }

    public function download(Client $client, ClientDocument $document): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return ApiError::notFound('Document not found.');
        }

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'client-documents.stream',
                now()->addSeconds(60),
                ['document' => $document->id]
            ),
            'expires_in' => 60,
        ]);
    }

    public function destroy(Client $client, ClientDocument $document): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return ApiError::notFound('Document not found.');
        }

        DB::transaction(function () use ($document): void {
            Storage::disk('local')->delete($document->storage_path);
            $document->delete();
        });

        return response()->json([], 204);
    }

    public function stream(ClientDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->file_name);
    }
}
