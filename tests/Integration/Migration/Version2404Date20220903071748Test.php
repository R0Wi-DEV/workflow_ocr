<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
 *
 *  @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\WorkflowOcr\Tests\Integration\Migration;

use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Migration\Version2404Date20220903071748;
use OCA\WorkflowOcr\Operation;
use OCP\AppFramework\App;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version2404Date20220903071748Test extends TestCase {

	/** @var ContainerInterface */
	private $container;

	/** @var IDBConnection */
	private $connection;

	protected function setUp() : void {
		parent::setUp();
		$app = new App(Application::APP_NAME);
		$this->container = $app->getContainer();
		$this->connection = $this->container->get(IDBConnection::class);
	}

	public function testMigration() {
		$operationColumnPre = '{"languages":["de","en"],"removeBackground":false}';
		$operationColumnPost = '{"languages":["deu","eng"],"removeBackground":false}';
		$id = 0;

		try {
			$query = $this->connection->getQueryBuilder();
			$query->insert('flow_operations')
				->values([
					'class' => $query->createNamedParameter(Operation::class),
					'name' => $query->createNamedParameter(''),
					'checks' => $query->createNamedParameter(json_encode([1])),
					'operation' => $query->createNamedParameter($operationColumnPre),
					'entity' => $query->createNamedParameter(File::class),
					'events' => $query->createNamedParameter('["\\OCP\\Files::postCreate"]')
				]);
			$query->executeStatement();
			$id = $query->getLastInsertId();

			/** @var Version2404Date20220903071748 */
			$migration = $this->container->get(Version2404Date20220903071748::class);

			$migration->changeSchema($this->createMock(IOutput::class), function () {
			}, []);

			$read = $this->connection->getQueryBuilder();
			$read->select('*')
				->from('flow_operations')
				->where($read->expr()->eq('id', $read->createNamedParameter($id)));
			$result = $read->executeQuery()->fetch();
			$this->assertEquals($operationColumnPost, $result['operation']);
		} finally {
			$deleteFlow = $this->connection->getQueryBuilder();
			$deleteFlow->delete('flow_operations')
				->where($query->expr()->eq('id', $deleteFlow->createNamedParameter($id)));
			$deleteFlow->executeStatement();

			$deleteMigration = $this->connection->getQueryBuilder();
			$deleteMigration->delete('migrations')
				->where($query->expr()->eq('app', $deleteMigration->createNamedParameter(Application::APP_NAME)))
				->andWhere($query->expr()->eq('version', $deleteMigration->createNamedParameter('2404Date20220903071748')));
			$deleteMigration->executeStatement();
		}
	}
}
