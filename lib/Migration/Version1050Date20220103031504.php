<?php

declare(strict_types=1);

namespace OCA\Mail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1050Date20220103031504 extends SimpleMigrationStep
{

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$accountsTable = $schema->getTable('mail_accounts');

		$accountsTable->addColumn('oauth_provider', 'string', [
			'notnull' => false,
			'default' => null,
			'length'  => 64,
		]);

		$accountsTable->addColumn('oauth_access_token', 'text', [
			'notnull' => false,
			'default' => null,
		]);

		$accountsTable->addColumn('oauth_refresh_token', 'text', [
			'notnull' => false,
			'default' => null,
		]);

		$accountsTable->addColumn('oauth_id_token', 'text', [
			'notnull' => false,
			'default' => null,
		]);

		$accountsTable->addColumn('oauth_expire_in', 'integer', [
			'notnull' => false,
			'default' => 0,
		]);

		return $schema;
	}
}
