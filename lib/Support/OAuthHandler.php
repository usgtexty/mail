<?php

declare(strict_types=1);

namespace OCA\Mail\Support;

use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\MailAccountMapper;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;

class OAuthHandler {

	/** @var MailAccount */
	private $account;

	/** @var IRequest */
	private $request;

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/** @var IClientService */
	private $clientService;

	/** @var IClient */
	private $client;

	/** @var MailAccountMapper */
	private $mapper;

	/**
	 * OAuth Manager constructor
	 *
	 * @param MailAccount $account
	 */
	public function __construct(
		IRequest $request,
		IConfig $config,
		ICrypto $crypto,
		IClientService $clientService,
		MailAccountMapper $mapper
	) {
		$this->request = $request;
		$this->crypto = $crypto;
		$this->config = $config;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
		$this->mapper = $mapper;
	}

	public function authorize(string $provider) {
		$method = $provider . 'Authorize';
		if (!method_exists($this, $method)) {
			throw new \Exception('Unsupported Provider!');
		}

		return $this->$method();
	}

	private function microsoftAuthorize(): array {
		$clientId =  $this->config->getSystemValue('ms_azure_client_id');
		$clientSecret =  $this->config->getSystemValue('ms_azure_client_secret');

		$request = $this->client->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
			'body' => [
				'client_id' => $clientId,
				'client_secret' => $clientSecret,
				'scope' => 'offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
				'redirect_uri' => 'http://localhost:8090/auth.php', // TODO: Change this
				'grant_type' => 'authorization_code',
				'code' => $this->request->getParam('code'),
			]
		]);

		$body = json_decode($request->getBody(), true);
		$decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $body['id_token'] ?? '')[1]))), true);

		return [
			'name' => $decoded['name'] ?? null,
			'email' => $decoded['preferred_username'] ?? null,
			'access_token' => $body['access_token'] ?? null,
			'refresh_token' => $body['refresh_token'] ?? null,
			'id_token' => $body['id_token'] ?? null,
			'expire_in' => isset($body['expire_in']) ? time() + $body['expire_in'] : null,
			'imap_host' => 'outlook.office365.com',
			'imap_port' => 993,
			'imap_ssl_mode' => 'ssl',
			'pop_host' => 'outlook.office365.com',
			'pop_port' => 995,
			'pop_ssl_mode' => 'ssl',
			'smtp_host' => 'smtp.office365.com',
			'smtp_port' => 587,
			'smtp_ssl_mode' => 'tls',
		];
	}

	private function googleAuthorize(): array {
		return [
			'name' => null,
			'email' => null,
			'access_token' => null,
			'refresh_token' => null,
			'id_token' => null,
			'expire_in' => null,
			'imap_host' => 'imap.gmail.com',
			'imap_port' => 993,
			'imap_ssl_mode' => 'ssl',
			'pop_host' => 'pop.gmail.com',
			'pop_port' => 995,
			'pop_ssl_mode' => 'ssl',
			'smtp_host' => 'smtp.gmail.com',
			'smtp_port' => 587,
			'smtp_ssl_mode' => 'tls',
		];
	}

	public function refresh(MailAccount $account) {
		$this->account = $account;
		
		$method = $account->getOauthProvider() . 'Refresh';
		if (!method_exists($this, $method)) {
			throw new \Exception('Unsupported Provider!');
		}

		return $this->$method();
	}

	private function microsoftRefresh() {
		$clientId =  $this->config->getSystemValue('ms_azure_client_id');
		$clientSecret =  $this->config->getSystemValue('ms_azure_client_secret');

		$request = $this->client->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
			'body' => [
				'client_id' => $clientId,
				'client_secret' => $clientSecret,
				'redirect_uri' => 'http://localhost:8090/auth.php', // TODO: Change this
				'grant_type' => 'refresh_token',
				'refresh_token' => $this->request->getParam('code'),
			]
		]);

		$body = json_decode($request->getBody(), true);

		return [
			'access_token' => null,
			'refresh_token' => null,
			'id_token' => null,
			'expire_in' => time(),
		];

		return $body['access_token'] ?? null;
	}

	public function googleRefresh() {
		return [
			'access_token' => null,
			'refresh_token' => null,
			'id_token' => null,
			'expire_in' => time(),
		];
	}
}
