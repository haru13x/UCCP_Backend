<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\AccountType;
use Illuminate\Http\Request;

class AccountGroupController extends Controller
{
    public function getGroups()
    {
        return response()->json(AccountGroup::where('is_active', 1)->get());
    }

    public function getTypesByGroup($groupId)
    {
        return response()->json(AccountType::where('group_id', $groupId)->where('is_active', 1)->get());
    }
}
