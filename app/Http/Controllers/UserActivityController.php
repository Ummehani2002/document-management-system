<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserActivityController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->query('user_id', 0);
        $action = trim((string) $request->query('action', ''));

        $query = UserActivity::query()
            ->with(['user', 'document.project', 'document.entity'])
            ->latest('id');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($action !== '') {
            $query->where('action', $action);
        }

        return view('user-activities.index', [
            'activities' => $query->paginate(25)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'actions' => UserActivity::ACTION_LABELS,
            'selectedUserId' => $userId,
            'selectedAction' => $action,
        ]);
    }
}
