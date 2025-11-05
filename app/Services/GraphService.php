<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;

class GraphService
{
    private Client $http;
    private array $cfg;

    public function __construct()
    {
        $this->http = new Client(['http_errors' => false, 'timeout' => 20]);
        $this->cfg = config('services.msgraph');
    }

        public function authUrl(): string
    {
        $params = [
            'client_id'     => $this->cfg['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $this->cfg['redirect_uri'],
            'response_mode' => 'query',
            'scope'         => $this->cfg['scope'],
            'state'         => bin2hex(random_bytes(16)),
            'prompt'        => 'select_account',
        ];
        Session::put('oauth_state', $params['state']);
        return $this->cfg['auth_url'].'?'.http_build_query($params);
    }

    public function exchangeCode(string $code): ?array
    {
        $data = [
            'client_id'     => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'grant_type'    => 'authorization_code',
            'scope'         => $this->cfg['scope'],
            'code'          => $code,
            'redirect_uri'  => $this->cfg['redirect_uri'],
        ];
        return $this->tokenPost($data);
    }

    public function refresh(): ?string
    {
        $rt = Session::get('refresh_token');
        if (!$rt) return null;
        $data = [
            'client_id'     => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'grant_type'    => 'refresh_token',
            'scope'         => $this->cfg['scope'],
            'refresh_token' => $rt,
        ];
        $t = $this->tokenPost($data);
        if ($t) {
            $this->saveToken($t);
            return Session::get('access_token');
        }
        $this->clearToken();
        return null;
    }

    private function tokenPost(array $form): ?array
    {
        $res = $this->http->post($this->cfg['token_url'], [
            'form_params' => $form,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ]);
        if ($res->getStatusCode() !== 200) return null;
        return json_decode((string) $res->getBody(), true);
    }

    public function saveToken(array $t): void
    {
        Session::put('access_token',  $t['access_token']);
        Session::put('refresh_token', $t['refresh_token'] ?? null);
        Session::put('expires_at',    time() + (int)$t['expires_in'] - 60);
    }

    public function clearToken(): void
    {
        Session::forget(['access_token','refresh_token','expires_at']);
    }

    public function ensureToken(): ?string
    {
        $at = Session::get('access_token');
        $exp = Session::get('expires_at', 0);
        if ($at && time() < $exp) return $at;
        return $this->refresh();
    }

    /* ===== Graph calls ===== */
    public function graph(string $method, string $path, ?array $body = null): array|false
    {
        $token = $this->ensureToken();
        if (!$token) return false;

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ];
        $opts = ['headers' => $headers];
        if (!is_null($body)) {
            $opts['json'] = $body;
        }

        $res = $this->http->request($method, rtrim($this->cfg['graph_base'], '/').$path, $opts);
        $code = $res->getStatusCode();
        $text = (string) $res->getBody();

        if ($code >= 200 && $code < 300) {
            return strlen($text) ? json_decode($text, true) : [];
        }

        // helpful debug during dev
        throw new \RuntimeException("Graph $method $path => HTTP $code\n".$text);
    }
}