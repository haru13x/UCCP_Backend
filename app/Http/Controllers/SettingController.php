<?php

namespace App\Http\Controllers;

use App\Models\ChurchLocation;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function ChurchLocation()
    {
        $data = ChurchLocation::all();
        return response()->json($data);
    }
}
