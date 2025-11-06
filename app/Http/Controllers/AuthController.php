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
        abort(400, 'Invalid OAuth state or missing code.');
    }
    $token = $graph->exchangeCode($req->code);
    if (!$token) abort(400, 'Token exchange failed.');
    $graph->saveToken($token);
    
    // Extract and store user email from token
    $tokenParts = explode(".", $token);
    if (count($tokenParts) >= 2) {
        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);
        if (isset($payload['preferred_username']) || isset($payload['email'])) {
            session(['user_email' => $payload['preferred_username'] ?? $payload['email']]);
        }
    }
    
    return redirect()->route('home');
}

    public function logout(GraphService $graph)
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