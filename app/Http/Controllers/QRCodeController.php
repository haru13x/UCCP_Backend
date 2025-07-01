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




class QRCodeController extends Controller
{
    public function generate($eventId)
{
    $event = Event::findOrFail($eventId);

    // Check if QR code already exists
    if ($event->qrcode) {
        return response()->json([
            'message' => 'QR already exists',
            'qr_url' => asset("storage/" . $event->qrcode->qr_path),
        ]);
    }

    // Generate QR code as PNG
    $fileName = "qrcodes/event-" . $event->id . ".png";
    $qrImage = QrCode::format('png')
        ->size(300)
        ->generate("EventID: {$event->id}");

    // Save to public storage
    Storage::disk('public')->put($fileName, $qrImage);

    // Save QR record
    $eventQr = new QR();
    $eventQr->barcode_id = $event->id;
    $eventQr->qr_path = $fileName;
    $eventQr->save();

    return response()->json([
        'message' => 'QR code generated successfully',
        'qr_url' => asset("storage/" . $fileName),
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
    $qr = QR::where('barcode_id', $eventId)->first();

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
