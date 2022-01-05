<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\IMAP;

use Horde_Imap_Client_Socket;
use OCA\Mail\Account;
use OCA\Mail\Cache\Cache;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Support\OAuthHandler;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\Security\ICrypto;

class IMAPClientFactory {

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/** @var ICacheFactory */
	private $cacheFactory;

	/** @var AccountService */
	private $accountService;

	/** @var OAuthHandler */
	private $oAuthHandler;

	private $cache = [];

	/**
	 * @param ICrypto $crypto
	 * @param IConfig $config
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(ICrypto $crypto, IConfig $config, ICacheFactory $cacheFactory, AccountService $accountService) {
		$this->crypto = $crypto;
		$this->config = $config;
		$this->cacheFactory = $cacheFactory;
		$this->accountService = $accountService;
		$this->oAuthHandler = new OAuthHandler();
	}

	/**
	 * @param Account $account
	 * @return Horde_Imap_Client_Socket
	 */
	public function getClient(Account $account): Horde_Imap_Client_Socket {
		if (!isset($this->cache[$account->getId()])) {
			$host = $account->getMailAccount()->getInboundHost();
			$user = $account->getMailAccount()->getInboundUser();
			$password = $account->getMailAccount()->getInboundPassword();
			$password = $this->crypto->decrypt($password);
			$token = $account->getMailAccount()->getOauthAccessToken();
			$token = null;
			if ($password === 'XOAUTH2') {
				$token = $this->oAuthHandler->getToken($account->getMailAccount(), $this->accountService);
			}
			$port = $account->getMailAccount()->getInboundPort();
			$sslMode = $account->getMailAccount()->getInboundSslMode();
			if ($sslMode === 'none') {
				$sslMode = false;
			}

			$params = [
				'username' => $user,
				'password' => $password,
				'xoauth2_token' => (new \Horde_Imap_Client_Password_Xoauth2($user, $token))->getPassword(),
				'hostspec' => $host,
				'port' => $port,
				'secure' => $sslMode,
				'timeout' => (int)$this->config->getSystemValue('app.mail.imap.timeout', 5),
				'context' => [
					'ssl' => [
						'verify_peer' => $this->config->getSystemValueBool('app.mail.verify-tls-peer', true),
						'verify_peer_name' => $this->config->getSystemValueBool('app.mail.verify-tls-peer', true),
					],
				],
			];
			if ($this->cacheFactory->isAvailable()) {
				$params['cache'] = [
					'backend' => new Cache([
						'cacheob' => $this->cacheFactory->createDistributed(md5((string)$account->getId())),
					])];
			}
			if ($this->config->getSystemValue('debug', false)) {
				$params['debug'] = $this->config->getSystemValue('datadirectory') . '/horde_imap.log';
			}
			$this->cache[$account->getId()] = new Horde_Imap_Client_Socket($params);
		}

		return $this->cache[$account->getId()];
	}
}
