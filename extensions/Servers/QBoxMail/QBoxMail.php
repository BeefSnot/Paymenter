<?php

namespace Paymenter\Extensions\Servers\QBoxMail;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

/**
 * Class QBoxMail
 */
class QBoxMail extends Server
{
    /**
     * Get the configuration options for the QBoxMail extension.
     *
     * @param array $values
     * @return array
     */
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'QBoxMail API URL',
                'type' => 'text',
                'default' => 'https://api.qboxmail.com/v1/',
                'description' => 'QBoxMail API URL',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'QBoxMail API Key',
                'type' => 'text',
                'default' => '',
                'description' => 'Your QBoxMail API key',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    /**
     * Test the configuration by making a request to the QBoxMail API.
     *
     * @return bool|string
     */
    public function testConfig(): bool|string
    {
        try {
            $this->request('domains', 'GET');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    /**
     * Make a request to the QBoxMail API.
     *
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function request($endpoint, $method = 'get', $data = []): array
    {
        $url = rtrim($this->config('api_url'), '/') . '/' . ltrim($endpoint, '/');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config('api_key'),
            'Accept' => 'application/json',
        ])->$method($url, $data);

        if (!$response->successful()) {
            throw new \Exception('API request failed: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Get the product configuration options.
     *
     * @param array $values
     * @return array
     */
    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'domain',
                'label' => 'Domain Name',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'mailbox_quota',
                'label' => 'Mailbox Quota (MB)',
                'type' => 'number',
                'default' => 1024,
                'required' => true,
            ],
        ];
    }

    /**
     * Create a mailbox on QBoxMail.
     *
     * @param Service $service
     * @param array $settings
     * @param array $properties
     * @return bool
     */
    public function createMailbox(Service $service, $settings, $properties)
    {
        $data = [
            'domain' => $properties['domain'],
            'mailbox' => [
                'name' => $service->user->email,
                'quota' => $settings['mailbox_quota'],
            ],
        ];

        $this->request('mailboxes', 'POST', $data);

        return true;
    }

    /**
     * Suspend a mailbox on QBoxMail.
     *
     * @param Service $service
     * @param array $settings
     * @param array $properties
     * @return bool
     */
    public function suspendMailbox(Service $service, $settings, $properties)
    {
        $this->request('mailboxes/' . $service->user->email . '/suspend', 'POST');

        return true;
    }

    /**
     * Unsuspend a mailbox on QBoxMail.
     *
     * @param Service $service
     * @param array $settings
     * @param array $properties
     * @return bool
     */
    public function unsuspendMailbox(Service $service, $settings, $properties)
    {
        $this->request('mailboxes/' . $service->user->email . '/unsuspend', 'POST');

        return true;
    }

    /**
     * Terminate a mailbox on QBoxMail.
     *
     * @param Service $service
     * @param array $settings
     * @param array $properties
     * @return bool
     */
    public function terminateMailbox(Service $service, $settings, $properties)
    {
        $this->request('mailboxes/' . $service->user->email, 'DELETE');

        return true;
    }

    /**
     * Get the view for a mailbox on QBoxMail.
     *
     * @param Service $service
     * @param string $view
     * @return HtmlString
     */
    public function getView(Service $service, $view)
    {
        $mailbox = $this->request('mailboxes/' . $service->user->email, 'GET');

        return new HtmlString(view('extensions.qboxmail.view', compact('mailbox'))->render());
    }
}