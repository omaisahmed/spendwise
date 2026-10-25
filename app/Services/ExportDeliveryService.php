<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Native\Mobile\Facades\Share;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ExportDeliveryService
{
    public function deliver(array $file, string $message): BinaryFileResponse|RedirectResponse
    {
        if ($this->nativeShareAvailable()) {
            try {
                Share::file('SpendWise export', $message, $file['path']);
                $this->pruneOldExports();

                return back()->with('success', $message.' Choose an app in the share sheet to save or send it.');
            } catch (Throwable $exception) {
                report($exception);

                return back()->with('error', 'The export was created but the share sheet could not be opened.');
            }
        }

        return response()
            ->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend(true);
    }

    public function nativeShareAvailable(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        try {
            return (new \ReflectionFunction('nativephp_call'))->isInternal();
        } catch (Throwable) {
            return false;
        }
    }

    private function pruneOldExports(): void
    {
        $directory = storage_path('app/exports');

        if (! File::isDirectory($directory)) {
            return;
        }

        $cutoff = now()->subDay()->getTimestamp();

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
