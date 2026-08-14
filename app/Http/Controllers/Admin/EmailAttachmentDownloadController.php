<?php

namespace App\Http\Controllers\Admin;

use App\Services\GraphMailboxService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailAttachmentDownloadController
{
    public function __invoke(string $folder, string $messageId, string $attachmentId, GraphMailboxService $graph): StreamedResponse|Response
    {
        $decodedMessageId = $this->decode($messageId);
        $decodedAttachmentId = $this->decode($attachmentId);

        $file = $graph->downloadAttachment($decodedMessageId, $decodedAttachmentId);

        return response()->streamDownload(
            function () use ($file) {
                echo $file['content'];
            },
            $file['name'],
            [
                'Content-Type' => $file['contentType'] ?? 'application/octet-stream',
            ]
        );
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
