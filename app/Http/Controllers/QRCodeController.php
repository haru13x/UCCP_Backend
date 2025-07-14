<?php
// app/Http/Controllers/EventQrCodeController.php
namespace App\Http\Controllers;
// app/Http/Controllers/EventQrCodeController.php

// use Illuminate\Support\Facades\Storage;
// use SimpleSoftwareIO\QrCode\Facades\QrCode;

// use App\Models\Event;
// use App\Models\QR;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Event;
use App\Models\QR;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;



class QRCodeController extends Controller
{
    public function generate($eventId)
    {
        $event = Event::findOrFail($eventId);
        $timestamp = Carbon::now()->format('Ymd_His');
        $barcode = $timestamp . $event->id;
        $event->update(['barcode' => $barcode]); // Ensure barcode_id is set
        // Check if QR already exists
        if ($event->qrcode) {
            return response()->json([
                'message' => 'QR already exists',
                'qr_url' => asset("storage/" . $event->qrcode->qr_path),
            ]);
        }

        // Generate QR code binary
        $qrImage = \QrCode::format('png')
            ->size(300)
            ->generate("{$barcode}");

        // Step 1: Save to a temporary path
        $tmpPath = storage_path('app/tmp-qr.png');
        File::put($tmpPath, $qrImage);

        // Step 2: Move to public storage path manually
      $fileName = 'event-' . $barcode . '.png';

        $publicPath = public_path('storage/qrcodes');

        // Ensure directory exists
        if (!File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0755, true);
        }

        $finalPath = $publicPath . '/' . $fileName;
        File::move($tmpPath, $finalPath);

        // Step 3: Save in DB
        $qr = new QR();
        $qr->barcode = $barcode;
        $qr->event_id = $event->id;
        $qr->qr_path = 'qrcodes/' . $fileName;
        $qr->save();

        return response()->json([
            'message' => 'QR code generated successfully',
            'qr_url' => asset('storage/qrcodes/' . $fileName),
        ]);
    }
    // public function generate($eventId)
    // {
    //     $event = Event::findOrFail($eventId);

    //     // Check if already generated
    //     if ($event->qrcode) {
    //         return response()->json([
    //             'message' => 'QR already exists',
    //             'qr_url' => asset("storage/" . $event->qrcode->qr_path),
    //         ]);
    //     }

    //     $fileName = "qrcodes/event-" . $event->id . ".png";
    //     $qrImage = QrCode::format('png')->size(300)->generate("EventID: {$event->id}");

    //     Storage::disk('public')->put($fileName, $qrImage);

    //     $eventQr = new QR();
    //     $eventQr->barcode_id = $event->id;
    //     $eventQr->qr_path = $fileName;
    //     $eventQr->save();

    //     return response()->json([
    //         'message' => 'QR code generated successfully',
    //         'qr_url' => asset("storage/" . $fileName),
    //     ]);
    // }

    public function get($eventId)
    {
        $qr = QR::where('event_id', $eventId)->first();

        if ($qr && $qr->qr_path) {
            return response()->json([
                'success' => true,
                'qr_url' => asset('storage/' . $qr->qr_path),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'QR code not found.',
            'qr_url' => null,
        ]);
    }
}
