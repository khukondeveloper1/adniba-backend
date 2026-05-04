<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/admin/emails
 */
class EmailController extends Controller
{
    public function __construct(
        private readonly EmailService $emailService
    ) {}

    /** GET /api/v1/admin/emails — email logs */
    public function index(Request $request): JsonResponse
    {
        $logs = $this->emailService->getLogs(
            $request->query('user_id') ? (int) $request->query('user_id') : null
        );

        return response()->json(['status' => 'ok', 'data' => $logs]);
    }

    /** POST /api/v1/admin/emails/send — send to single user */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $log  = $this->emailService->sendToUser(
            $user,
            $request->input('subject'),
            $request->input('body')
        );

        return response()->json([
            'status'  => 'ok',
            'message' => 'Email queued.',
            'log_id'  => $log->id,
        ]);
    }

    /** POST /api/v1/admin/emails/broadcast — send to ALL active users */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $count = $this->emailService->broadcast(
            $request->input('subject'),
            $request->input('body')
        );

        return response()->json([
            'status'  => 'ok',
            'message' => "Email queued for {$count} users.",
            'count'   => $count,
        ]);
    }
}
