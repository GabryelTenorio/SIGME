<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\PrivateAttachment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PrivateAttachmentController extends Controller
{
    public function store(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('update', $o);
        $file = $r->validate(['evidence' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf'])['evidence'];
        $path = $file->store('service-orders/'.$o->id, 'local');
        $a = $o->attachments()->create(['uploaded_by' => $r->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
        ServiceOrderHistory::query()->create(['service_order_id' => $o->id, 'actor_id' => $r->user()->id, 'event_type' => 'attachment_added', 'metadata' => ['attachment_id' => $a->id, 'name' => $a->original_name]]);

        return back()->with('success', 'Evidência privada anexada.');
    }

    public function storeForOccurrence(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('addEvidence', $occurrence);

        $file = $request->validate([
            'evidence' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ])['evidence'];
        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $occurrence, $file, &$storedPath): void {
                $current = Occurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
                $this->authorize('addEvidence', $current);

                if ($current->attachments()->count() >= 20) {
                    throw ValidationException::withMessages([
                        'evidence' => 'Esta ocorrência já atingiu o limite total de 20 arquivos.',
                    ]);
                }

                $storedPath = $file->store('occurrences/'.$current->id, 'local');
                if ($storedPath === false) {
                    throw new RuntimeException('Não foi possível armazenar a evidência privada.');
                }

                $attachment = $current->attachments()->create([
                    'uploaded_by' => $request->user()->id,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => (int) $file->getSize(),
                ]);
                OccurrenceHistory::query()->create([
                    'occurrence_id' => $current->id,
                    'actor_id' => $request->user()->id,
                    'event_type' => 'attachment_added',
                    'metadata' => ['attachment_id' => $attachment->id, 'name' => $attachment->original_name],
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        return back()->with('success', 'Evidência privada adicionada à ocorrência.');
    }

    public function download(Request $request, PrivateAttachment $attachment): StreamedResponse
    {
        $resource = $attachment->attachable;
        abort_unless($resource instanceof ServiceOrder || $resource instanceof Occurrence, 404);
        abort_unless($request->user()->can('view', $resource), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
