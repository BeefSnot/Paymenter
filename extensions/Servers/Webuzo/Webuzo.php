<?php

namespace Paymenter\Extensions\Servers\Webuzo;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

/**
 * Class Webuzo
 */
class Webuzo extends Server
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'Webuzo URL',
                'type' => 'text',
                'default' => 'https://example.com/',
                'description' => 'Webuzo URL',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'Webuzo API Key',
                'type' => 'text',
                'default' => 'webuzo_abcdefgh12345678',
                'description' => 'Webuzo API Key',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('/api/list-users', 'GET');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    public function request($url, $method = 'get', $data = []): array
    {
        $req_url = rtrim($this->config('host'), '/') . $url;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config('api_key'),
            'Accept' => 'application/json',
        ])->$method($req_url, $data);

        if (!$response->successful()) {
            throw new \Exception($response->json()['errors'][0]['detail']);
        }

        return $response->json() ?? [];
    }

    public function getProductConfig($values = []): array
    {
        $users = $this->request('/api/list-users');
        $userList = [];
        foreach ($users['data'] as $user) {
            $userList[$user['id']] = $user['name'];
        }

        return [
            [
                'name' => 'user_id',
                'label' => 'User',
                'type' => 'select',
                'options' => $userList,
                'required' => true,
            ],
        ];
    }

    public function createServer(Service $service, $settings, $properties)
    {
        $data = [
            'user_id' => $settings['user_id'],
            'domain' => $properties['domain'],
            'package' => $settings['package'],
        ];

        $this->request('/api/create-server', 'POST', $data);

        return true;
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        $this->request('/api/suspend-server/' . $properties['server_id'], 'POST');

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $this->request('/api/unsuspend-server/' . $properties['server_id'], 'POST');

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        $this->request('/api/terminate-server/' . $properties['server_id'], 'DELETE');

        return true;
    }

    public function getView(Service $service, $view)
    {
        $server = $this->request('/api/servers/' . $service->id, 'GET');

        return new HtmlString(view('extensions.webuzo.view', compact('server'))->render());
    }
}