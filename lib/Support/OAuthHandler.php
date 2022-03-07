<?php

declare(strict_types=1);

namespace OCA\Mail\Support;

use OC;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Service\AccountService;

class OAuthHandler {

	/** @var MailAccount */
	private $account;

	/** @var IRequest */
	private $request;

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/** @var IClient */
	private $client;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var AccountService */
	private $accountService;

	/** @var string */
	private $redirectUrl;

	/**
	 * OAuth Manager constructor
	 *
	 * @param MailAccount $account
	 */
	public function __construct() {
		$this->crypto = OC::$server->getCrypto();
		$this->config = OC::$server->getConfig();
		$this->request = OC::$server->getRequest();
		$this->urlGenerator = OC::$server->getURLGenerator();
		$this->client = OC::$server->getHTTPClientService()->newClient();
		$this->redirectUrl = $this->urlGenerator->linkToRouteAbsolute('mail.page.oauth');
	}

	public function getProviders(): array {
		$msQuery = http_build_query([
			'client_id' => $this->config->getSystemValue('ms_azure_client_id'),
			'response_type' => 'code',
			'response_mode' => 'query',
			'redirect_uri' => $this->redirectUrl,
			'scope' => 'profile openid offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
			'state' => 'microsoft',
			'prompt' => 'consent',
		]);

		$googleQuery = http_build_query([
			'client_id' => $this->config->getSystemValue('google_client_id'),
			'access_type' => 'offline',
			'include_granted_scopes' => 'true',
			'response_type' => 'code',
			'redirect_uri' => $this->redirectUrl,
			'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://mail.google.com/',
			'state' => 'google',
			'prompt' => 'consent',
		]);

		return [
			'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $msQuery,
			'google' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $googleQuery,
		];
	}

	public function authorize(string $provider) {
		$method = $provider . 'Authorize';
		if (!method_exists($this, $method)) {
			throw new \Exception('Unsupported Provider!');
		}

		return static::$method();
	}

	private function microsoftAuthorize(): array {
		$clientId =  $this->config->getSystemValue('ms_azure_client_id');
		$clientSecret =  $this->config->getSystemValue('ms_azure_client_secret');
		$request = $this->client->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
			'body' => [
				'client_id' => $clientId,
				'client_secret' => $clientSecret,
				'scope' => 'offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
				'redirect_uri' => $this->redirectUrl,
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
			'expire_in' => isset($body['expires_in']) ? time() + $body['expires_in'] : null,
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
		$clientId =  $this->config->getSystemValue('google_client_id');
		$clientSecret =  $this->config->getSystemValue('google_client_secret');
		$request = $this->client->post('https://oauth2.googleapis.com/token', [
			'body' => [
				'client_id' => $clientId,
				'client_secret' => $clientSecret,
				'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://mail.google.com/',
				'redirect_uri' => $this->redirectUrl,
				'grant_type' => 'authorization_code',
				'code' => $this->request->getParam('code'),
				'access_type' => 'offline',
				'include_granted_scopes' => 'true',
			]
		]);

		$body = json_decode($request->getBody(), true);
		$decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $body['id_token'] ?? '')[1]))), true);

		return [
			'name' => $decoded['name'] ?? null,
			'email' => $decoded['email'] ?? null,
			'access_token' => $body['access_token'] ?? null,
			'refresh_token' => $body['refresh_token'] ?? null,
			'id_token' => $body['id_token'] ?? null,
			'expire_in' => isset($body['expires_in']) ? time() + $body['expires_in'] : null,
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

	public function getToken(MailAccount $account, AccountService $accountService) {
		$this->account = $account;
		$this->accountService = $accountService;

		$token = $account->getOauthAccessToken();
		$token = $this->crypto->decrypt($token);
		$expire = (int) $this->account->getOauthExpireIn();

		if ($expire > time()) {
			return $token;
		}
		
		$method = $account->getOauthProvider() . 'Refresh';
		if (!method_exists($this, $method)) {
			throw new \Exception('Unsupported Provider!');
		}

		$res = $this->$method();
		if ($res['status'] !== 'success') {
			return $token;
		}

		$this->account->setOauthAccessToken($this->crypto->encrypt($res['access_token']));
		$this->account->setOauthRefreshToken($this->crypto->encrypt($res['refresh_token']));
		$this->account->setOauthIdToken($this->crypto->encrypt($res['id_token']));
		$this->account->setOauthExpireIn($res['expire_in']);
		$this->accountService->save($this->account);

		return $res['access_token'];
	}

	private function microsoftRefresh() {
		$clientId =  $this->config->getSystemValue('ms_azure_client_id');
		$clientSecret =  $this->config->getSystemValue('ms_azure_client_secret');
		$refreshToken = $this->account->getOauthRefreshToken();
		$refreshToken = $this->crypto->decrypt($refreshToken);

		try {
			$request = $this->client->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
				'body' => [
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
					'redirect_uri' => $this->redirectUrl,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				]
			]);

			$body = json_decode($request->getBody(), true);

			return [
				'status' => 'success',
				'message' => 'Token has been fetched.',
				'access_token' => $body['access_token'],
				'refresh_token' => $body['refresh_token'],
				'id_token' => $body['id_token'],
				'expire_in' => time() + $body['expires_in'],
			];
		} catch (\Exception $e) {
			return [
				'status' => 'error',
				'message' => $e->getMessage()
			];
		}
	}

	public function googleRefresh() {
		$clientId =  $this->config->getSystemValue('google_client_id');
		$clientSecret =  $this->config->getSystemValue('google_client_secret');
		$refreshToken = $this->account->getOauthRefreshToken();
		$refreshToken = $this->crypto->decrypt($refreshToken);

		try {
			$request = $this->client->post('https://oauth2.googleapis.com/token', [
				'body' => [
					'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://mail.google.com/',
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
					'redirect_uri' => $this->redirectUrl,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
					'access_type' => 'offline',
					'include_granted_scopes' => 'true',
				]
			]);

			$body = json_decode($request->getBody(), true);

			return [
				'status' => 'success',
				'message' => 'Token has been fetched.',
				'access_token' => $body['access_token'],
				'refresh_token' => $refreshToken,
				'id_token' => $body['id_token'],
				'expire_in' => time() + $body['expires_in'],
			];
		} catch (\Exception $e) {
			return [
				'status' => 'error',
				'message' => $e->getMessage()
			];
		}
	}
}
