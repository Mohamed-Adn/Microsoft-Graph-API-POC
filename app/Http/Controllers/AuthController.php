<?php

namespace App\Http\Controllers;

use App\Services\GraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function login(GraphService $graph)
    {
        return redirect()->away($graph->authUrl());
    }

    public function callback(Request $req, GraphService $graph)
    {
        if (!$req->has(['code','state']) || $req->state !== Session::get('oauth_state')) {
            abort(400, 'Invalid OAuth state or missiong code.');
        }
        $token = $graph->exchangeCode($req->code);
        if (!$token) abort(400, 'Token exchange failed.');
        $graph->saveToken($token);
        return redirect()->route('home');
    }

    public function logout(GraphService $grpah)
    {
        $graph->clearToken();
        return redirect()->route('home');
    }

    public function tokenInfo()
    {
        $token = Session::get('access_token');
        if (!$token) return response('No Token');
        $parts = explode(".", $token);
        $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true) ?: [];
        return response()->json([
            'aud' => $payload['aud'] ?? null,
            'scp' => $payload['scp'] ?? null,
            'exp' => isset($payload['exp']) ? date('c', $payload['exp']) : null,
            'tid' => $payload['tid'] ?? null,
            'upn' => $payload['upn'] ?? null,
            'preferred_username' => $payload['preferred_username'] ?? null,
        ]);
    }
}