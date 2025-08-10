<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserDetails;
use PhpOffice\PhpSpreadsheet\Shared\Date; // This is where excelToDateTimeObject comes from
use Carbon\Carbon;

use Dotenv\Validator;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    // In UserController.php
    public function updateStatus(Request $request)
    {
        $id = $request->user_id ?? '';
        $status = $request->status;
        $message = $status == 1 ? 'Enable' : 'Disbaled';
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json(['msg' => 'User Not Found'], 500);
        }
        if ($status) {
            $user->where('id', $id)->update([
                'status_id' => $status
            ]);
        }
        return response()->json('Successfully ' . $message . 'User', 200);
    }


    public function index(Request $request)
    {
        $query = User::where('is_request', 0);
        if ($request->has('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhereHas('details', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                    });
            });
        }
        $users = $query->with('accountType')->orderBy('name', 'asc')->get(); // Use ->get() instead of ->all()
        return response()->json($users, 200);
    }
    public function approveRequest($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $apiToken = Str::random(60); // Generate 60-char token
        $user->api_token = $apiToken; // Assign the token to the user
        $user->is_request = 0;
        $user->status_id = 1;
        $user->save();

        return response()->json(['message' => 'User approved successfully'], 200);
    }

    public function requestRegistration(Request $request)
    {
        $query = User::where('is_request', 1)->with('accountType');

        if ($request->has('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhereHas('details', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                    });
            });
        }

        return response()->json($query->get(), 200);
    }
    public function uploadUsers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        $rows = $data[0]; // first sheet
        $existingEmails = [];
        $createdUsers = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // skip header

                $username = trim($row[0]); // 2nd column username
                $email = trim($row[1]); // assuming 3rd column is email

                $firstName = trim($row[2]);
                $lastName = trim($row[3]);
                $middleName = $row[4];

                // ✅ Convert Excel serial number to Y-m-d date
                $birthdateRaw = $row[5];

                if (is_numeric($birthdateRaw)) {
                    // Convert Excel serial number to Carbon date
                    $birthdate = Carbon::instance(Date::excelToDateTimeObject($birthdateRaw))->format('Y-m-d');
                } else {
                    // Assume it's already a valid date string
                    $birthdate = Carbon::parse($birthdateRaw)->format('Y-m-d');
                }


                if (User::where('email', $email)->exists()) {
                    $existingEmails[] = $email;
                    continue;
                }

                $apiToken = Str::random(60);

                $user = User::create([
                    'username' => $username,
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'password' => Hash::make($username),
                    'api_token' => $apiToken,
                    'role_id' => 2,
                    'status_id' => 1,
                    'is_request' => 0,
                ]);

                $user->details()->create([
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'middle_name' => $middleName,
                    'birthdate' => $birthdate,
                    'status_id' => 1,
                    'created_by' => auth()->id() ?? 1,
                ]);

                $selectedAccountTypes = $request->account_type_id ?? [];
                $groupId = $request->account_group_id ?? null;
                // If you also want to add account type and group info, map it from columns
                foreach ($selectedAccountTypes as $typeId) {
                    \App\Models\UserAccountType::create([
                        'user_id' => $user->id,
                        'account_type_id' => $typeId,
                        'group_id' => $groupId,
                        'status' => 1,
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
                $createdUsers[] = $user;
            }

            DB::commit();

            return response()->json([
                'message' => 'Upload processed.',
                'existing_emails' => $existingEmails,
                'created_users' => $createdUsers,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchUsers(Request $request)
    {

        $search = $request->search ?? $request->query('search');
        $eventId = $request->event_id ?? $request->query('event_id');
        $users = User::where('is_request', 0)->with(['details', 'registeredUsers' => function ($q) use ($eventId) {
            $q->where('event_id', $eventId);
        }])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('details', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%");
                })->orWhere('id', 'like', "%$search%");
            })
            ->take(20)
            ->get()
            ->map(function ($user) {
                $user->is_registered = $user->registeredUsers->isNotEmpty();
                unset($user->registeredUsers); // optional cleanup
                return $user;
            });


        return response()->json($users);
    }

    public function register(Request $request)
    {
        DB::connection('mysql')->beginTransaction();
        try {
            if ($request->is_request === 1) {
                $apiToken = null;
                $status_id = 0;
                $name =  $request->last_name . ' ' . $request->first_name;
            } else {
                $apiToken = Str::random(60); // Generate 60-char token
                $status_id = 1;
                $name = $request->lastName . ' ' . $request->firstName;
            }

            $user = User::create([
                'username' => $request->username ?? $request->email, // Use email as username if not provided
                'name' =>  $name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'api_token' => $apiToken,
                'role_id' => $request->role ?? 2, // Ensure this is passed in the request
                'status_id' => $status_id,
                'is_request' => $request->is_request ?? 0,
            ]);

            $user->details()->create([
                'user_id' => $user->id,
                'first_name' => $request->first_name ?? $request->firstName,
                'last_name' => $request->last_name ?? $request->lastName,
                'middle_name' => $request->middle_name,
                'birthdate' => $request->birthdate,
                'sex_id' => $request->gender,
                'status_id' => 1,
                'phone_number' => $request->phone,
                'created_by' => $user->id,

            ]);

            $selectedAccountTypes = $request->account_type_id ?? [];

            // Get existing account types for the user
            $existingAccountTypes = \App\Models\UserAccountType::where('user_id', $user->id)->get();

            $existingTypeIds = $existingAccountTypes->pluck('account_type_id')->toArray();

            // Mark unchecked ones as inactive
            foreach ($existingAccountTypes as $existing) {
                if (!in_array($existing->account_type_id, $selectedAccountTypes)) {
                    $existing->update(['status' => 2]); // mark as inactive
                } else {
                    $existing->update(['status' => 1]); // ensure active
                }
            }

            // Add new ones that don't exist yet
            foreach ($selectedAccountTypes as $typeId) {
                if (!in_array($typeId, $existingTypeIds)) {
                    \App\Models\UserAccountType::create([
                        'user_id' => $user->id,
                        'account_type_id' => $typeId,
                        'group_id' => $request->account_group_id ?? $request->accountGroupId,
                        'status' => 1,
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
            }

            DB::connection('mysql')->commit();
            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->load('details', 'role', 'accountType'),
                'api_token' => $apiToken,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)
            ->orWhere('username', $request->email)
            ->where('is_request', 0)->with('details', 'role', 'accountType')->first();
        if ($user->is_request == 1) {
            return response()->json(['msg' => 'User is not confirm please contact the admin'], 201);
        }
        if ($user->status_id == 0) {
            return response()->json(['msg' => 'This User is Inactive Please contact the Admin'], 201);
        }
        if ($user->status_id != 1) {
            return response()->json(['msg' => 'This User is Inactive ,  Please contact the Admin'], 201);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        // Generate token
        if (!$user->api_token) {
            $user->api_token = Str::random(60);
            $user->save();
        }
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'api_token' => $user->api_token
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'username' => 'required|string',
            'email' => 'required|string',
            'role' => 'required|exists:roles,id',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'accountGroupId' => 'nullable|integer',
            'account_type_id' => 'array',
            'account_type_id.*' => 'integer|exists:account_types,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::findOrFail($request->user_id);
            $details = $user->details ?: new \App\Models\UserDetails(['user_id' => $user->id]);

            // Update user basic info
            $user->update([
                'name' =>  $request->lastName . ' ' .  $request->firstName ?? $user->name,
                'username' => $request->username,
                'email' => $request->email,
                'role_id' => $request->role,
            ]);

            if ($request->filled('password')) {
                $user->password = bcrypt($request->password);
                $user->save();
            }

            // Update or create user details

            $details->first_name = $request->firstName;
            $details->middle_name = $request->middleName ?? '';
            $details->last_name = $request->lastName;
            $details->birthdate = $request->birthdate;
            $details->address = $request->address;
            $details->phone_number = $request->phone;
            $details->sex_id = $request->gender;
            $details->save();

            // ✅ Handle Account Types
            $selectedAccountTypes = $request->account_type_id ?? [];

            // Get existing account types for the user
            $existingAccountTypes = \App\Models\UserAccountType::where('user_id', $user->id)->get();

            $existingTypeIds = $existingAccountTypes->pluck('account_type_id')->toArray();

            // Mark unchecked ones as inactive
            foreach ($existingAccountTypes as $existing) {
                if (!in_array($existing->account_type_id, $selectedAccountTypes)) {
                    $existing->update(['status' => 2]); // mark as inactive
                } else {
                    $existing->update(['status' => 1]); // ensure active
                }
            }

            // Add new ones that don't exist yet
            foreach ($selectedAccountTypes as $typeId) {
                if (!in_array($typeId, $existingTypeIds)) {
                    \App\Models\UserAccountType::create([
                        'user_id' => $user->id,
                        'account_type_id' => $typeId,
                        'group_id' => $request->accountGroupId,
                        'status' => 1,
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully.',
                'user' => $user->fresh(['details', 'accountType']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrganizer()
    {
        $user = User::where('role_id', 2)->where('status_id', 1)
            ->get();
        return response()->json($user, 200);
    }
}
