<?php

namespace Paymenter\Extensions\Servers\AzuraCast;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Class AzuraCast
 */
class AzuraCast extends Server
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'AzuraCast URL',
                'type' => 'text',
                'default' => 'https://example.com/',
                'description' => 'AzuraCast URL',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'AzuraCast API Key',
                'type' => 'text',
                'default' => 'azc_abcdefgh12345678',
                'description' => 'AzuraCast API Key',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('/api/stations', 'GET');
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
            throw new \Exception('API request failed: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function getProductConfig($values = []): array
    {
        $stations = $this->request('/api/stations');
        $stationList = [];
        foreach ($stations as $station) {
            $stationList[$station['id']] = $station['name'];
        }

        return [
            [
                'name' => 'station_id',
                'label' => 'Station',
                'type' => 'select',
                'description' => 'Select the station to manage',
                'options' => $stationList,
                'required' => true,
            ],
            [
                'name' => 'disk_quota',
                'label' => 'Disk Quota',
                'type' => 'number',
                'suffix' => 'MB',
                'required' => true,
                'validation' => 'numeric',
            ],
            [
                'name' => 'max_listeners',
                'label' => 'Max Listeners',
                'type' => 'number',
                'required' => true,
                'validation' => 'numeric',
            ],
        ];
    }

    public function createServer(Service $service, $settings, $properties)
    {
        if ($this->getServer($service->id, failIfNotFound: false)) {
            throw new \Exception('Server already exists');
        }
        $settings = array_merge($settings, $properties);

        $orderUser = $service->order->user;
        $user = $this->request('/api/users', 'get', ['search' => $orderUser->email])[0]['id'] ?? null;

        if (!$user) {
            $user = $this->request('/api/users', 'post', [
                'email' => $orderUser->email,
                'username' => (preg_replace('/[^a-zA-Z0-9]/', '', strtolower($orderUser->name)) ?? Str::random(8)) . '_' . Str::random(4),
                'name' => $orderUser->name,
            ])['id'];
        }

        $stationData = [
            'name' => $service->product->name . '-' . $service->id,
            'short_name' => Str::slug($service->product->name . '-' . $service->id),
            'description' => $service->product->description,
            'frontend_type' => 'shoutcast2',
            'backend_type' => 'liquidsoap',
            'radio_base_dir' => '/var/azuracast/stations',
            'radio_media_dir' => '/var/azuracast/stations/media',
            'disk_quota' => (int) $settings['disk_quota'],
            'max_listeners' => (int) $settings['max_listeners'],
            'owner_id' => (int) $user,
        ];

        $station = $this->request('/api/stations', 'post', $stationData);

        return [
            'server' => $station['id'],
            'link' => $this->config('host') . '/station/' . $station['short_name'],
        ];
    }

    private function getServer($id, $failIfNotFound = true, $raw = false)
    {
        try {
            $response = $this->request('/api/stations/' . $id);
        } catch (\Exception $e) {
            if ($failIfNotFound) {
                throw new \Exception('Server not found');
            } else {
                return false;
            }
        }
        if ($raw) {
            return $response;
        }

        return $response['id'] ?? false;
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/stations/' . $server . '/suspend', 'post');

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/stations/' . $server . '/unsuspend', 'post');

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/stations/' . $server, 'delete');

        return true;
    }

    public function getActions(Service $service)
    {
        $server = $this->getServer($service->id, raw: true);

        return [
            [
                'type' => 'button',
                'label' => 'Go to station',
                'url' => $this->config('host') . '/station/' . $server['short_name'],
            ],
        ];
    }
}