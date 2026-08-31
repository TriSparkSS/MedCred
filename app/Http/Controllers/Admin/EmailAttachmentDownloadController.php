<?php

namespace App\Http\Controllers\Admin;

use App\Services\GraphMailboxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailAttachmentDownloadController
{
    public function __invoke(
        Request $request,
        string $folder,
        string $messageId,
        string $attachmentId,
        GraphMailboxService $graph,
    ): StreamedResponse|Response {
        $decodedMessageId = $this->decode($messageId);
        $decodedAttachmentId = $this->decode($attachmentId);

        try {
            $file = $graph->downloadAttachment($decodedMessageId, $decodedAttachmentId);
        } catch (\RuntimeException $e) {
            abort(502, $e->getMessage());
        } catch (\Throwable) {
            abort(404, 'Attachment could not be retrieved.');
        }

        $contentType = (string) ($file['contentType'] ?? 'application/octet-stream');
        $name = (string) ($file['name'] ?? 'attachment');
        $forceDownload = $request->boolean('download');
        $disposition = ($forceDownload || ! $this->isInlineViewable($contentType))
            ? 'attachment'
            : 'inline';

        return response($file['content'], 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $this->contentDisposition($disposition, $name),
            'Content-Length' => (string) strlen($file['content']),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function isInlineViewable(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        return $type === 'application/pdf' || str_starts_with($type, 'image/');
    }

    protected function contentDisposition(string $disposition, string $filename): string
    {
        $safe = str_replace(['"', "\r", "\n"], '', $filename);
        $encoded = rawurlencode($safe);

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $disposition, $safe, $encoded);
    }

    protected function decode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        if ($decoded === false || $decoded === '') {
            abort(404);
        }

        return $decoded;
    }
}
