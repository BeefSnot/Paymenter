<?php

namespace Paymenter\Extensions\Servers\Namecheap;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

/**
 * Class Namecheap
 */
class Namecheap extends Server
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_user',
                'label' => 'Namecheap API User',
                'type' => 'text',
                'default' => '',
                'description' => 'Your Namecheap API username',
                'required' => true,
            ],
            [
                'name' => 'api_key',
                'label' => 'Namecheap API Key',
                'type' => 'text',
                'default' => '',
                'description' => 'Your Namecheap API key',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'client_ip',
                'label' => 'Client IP',
                'type' => 'text',
                'default' => '',
                'description' => 'Your IP address registered with Namecheap',
                'required' => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('namecheap.domains.getList', 'GET');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    public function request($command, $method = 'get', $data = []): array
    {
        $url = 'https://api.namecheap.com/xml.response';
        $params = array_merge([
            'ApiUser' => $this->config('api_user'),
            'ApiKey' => $this->config('api_key'),
            'UserName' => $this->config('api_user'),
            'ClientIp' => $this->config('client_ip'),
            'Command' => $command,
        ], $data);

        $response = Http::asForm()->$method($url, $params);

        if (!$response->successful()) {
            throw new \Exception('API request failed: ' . $response->body());
        }

        $xml = simplexml_load_string($response->body());
        if ($xml->Errors->Error) {
            throw new \Exception('API error: ' . (string)$xml->Errors->Error);
        }

        return json_decode(json_encode($xml), true);
    }

    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'domain',
                'label' => 'Domain Name',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function registerDomain(Service $service, $settings, $properties)
    {
        $data = [
            'DomainName' => $properties['domain'],
            'Years' => 1,
            'RegistrantFirstName' => $service->user->first_name,
            'RegistrantLastName' => $service->user->last_name,
            'RegistrantAddress1' => $service->user->address,
            'RegistrantCity' => $service->user->city,
            'RegistrantStateProvince' => $service->user->state,
            'RegistrantPostalCode' => $service->user->zip,
            'RegistrantCountry' => $service->user->country,
            'RegistrantPhone' => $service->user->phone,
            'RegistrantEmailAddress' => $service->user->email,
        ];

        $this->request('namecheap.domains.create', 'POST', $data);

        return true;
    }

    public function renewDomain(Service $service, $settings, $properties)
    {
        $data = [
            'DomainName' => $properties['domain'],
            'Years' => 1,
        ];

        $this->request('namecheap.domains.renew', 'POST', $data);

        return true;
    }

    public function getView(Service $service, $view)
    {
        $domain = $this->request('namecheap.domains.getInfo', 'GET', ['DomainName' => $service->domain]);

        return new HtmlString(view('extensions.namecheap.view', compact('domain'))->render());
    }
}