<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SA (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SA (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.13.0
 */

namespace App\Test\TestCase\Service\Secrets;

use App\Error\Exception\CustomValidationException;
use App\Model\Entity\Permission;
use App\Model\Entity\Role;
use App\Model\Table\PermissionsTable;
use App\Model\Table\SecretsTable;
use App\Service\Secrets\SecretsUpdateSecretsService;
use App\Test\Lib\AppTestCase;
use App\Utility\UserAccessControl;
use App\Utility\UuidFactory;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * \App\Test\TestCase\Service\Secrets\SecretsUpdateSecretsServiceTest Test Case
 *
 * @covers \App\Test\TestCase\Service\Secrets\SecretsUpdateSecretsServiceTest
 */
class SecretsUpdateSecretsServiceTest extends AppTestCase
{
    /**
     * @var SecretsTable
     */
    public $Secrets;

    /**
     * @var SecretsUpdateSecretsService
     */
    public $service;

    public $fixtures = [
        'app.Base/Groups', 'app.Base/GroupsUsers', 'app.Base/Permissions', 'app.Base/Resources', 'app.Base/Secrets',
        'app.Base/Users', 'app.Base/Gpgkeys', 'app.Base/Roles', 'app.Base/Profiles', 'app.Base/Avatars',
    ];

    public function setUp()
    {
        parent::setUp();
        $this->Secrets = TableRegistry::getTableLocator()->get('Secrets');
        $this->Permissions = TableRegistry::getTableLocator()->get('Permissions');
        $this->service = new SecretsUpdateSecretsService();
    }

    /* ************************************************************** */
    /* ADD SECRETS */
    /* ************************************************************** */

    public function testUpdateSecretsSuccess_AddSecrets()
    {
        list($resource1, $userAId, $userBId) = $this->insertFixture_UpdateSecretsSuccess_AddSecrets();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $data = [
            ['user_id' => $userBId, 'data' => Hash::get(self::getDummySecretData(), 'data')],
        ];

        $this->service->updateSecrets($uac, $resource1->id, $data);

        // Assert secrets
        $secrets = $this->Secrets->findByResourceId($resource1->id)->toArray();
        $this->assertCount(2, $secrets);
        $this->assertSecretExists($resource1->id, $userAId);
        $this->assertSecretExists($resource1->id, $userBId);
        $secret = $this->Secrets->findByResourceIdAndUserId($resource1->id, $userBId)->first();
        $this->assertEquals($data[0]['data'], $secret->data);
    }

    private function insertFixture_UpdateSecretsSuccess_AddSecrets()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $userBId = UuidFactory::uuid('user.id.betty');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);
        // Add Betty's permission without secret.
        $this->addPermission(PermissionsTable::RESOURCE_ACO, $resource1->id, PermissionsTable::USER_ARO, $userBId, Permission::OWNER);

        return [$resource1, $userAId, $userBId];
    }

    public function testUpdateSecretsError_AddSecrets_NotAllSecretsProvided()
    {
        list($resource1, $userAId, $userBId) = $this->insertFixture_UpdateSecretsError_AddSecrets_NotAllSecretsProvided();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $data = [];

        try {
            $this->service->updateSecrets($uac, $resource1->id, $data);
            $this->assertFalse(true, 'The test should catch an exception');
        } catch (CustomValidationException $e) {
            $this->assertUpdateSecretsValidationException($e, 'secrets_provided');
        }
    }

    private function insertFixture_UpdateSecretsError_AddSecrets_NotAllSecretsProvided()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $userBId = UuidFactory::uuid('user.id.betty');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);
        // Add Betty's permission without secret.
        $this->addPermission(PermissionsTable::RESOURCE_ACO, $resource1->id, PermissionsTable::USER_ARO, $userBId, Permission::OWNER);

        return [$resource1, $userAId, $userBId];
    }

    public function testUpdateSecretsError_AddSecrets_ValidationExceptions_UserWithoutAccess()
    {
        list($resource1, $userAId) = $this->insertFixture_UpdateSecretsError_AddSecrets_ValidationExceptions_UserWithoutAccess();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $userBId = UuidFactory::uuid('user.id.betty');
        $data = [['user_id' => $userBId, 'data' => Hash::get(self::getDummySecretData(), 'data')], ];

        try {
            $this->service->updateSecrets($uac, $resource1->id, $data);
            $this->assertFalse(true, 'The test should catch an exception');
        } catch (CustomValidationException $e) {
            $this->assertUpdateSecretsValidationException($e, '0.resource_id.has_resource_access');
        }
    }

    private function assertUpdateSecretsValidationException(CustomValidationException $e, string $errorFieldName)
    {
        $this->assertEquals("Could not validate secrets data.", $e->getMessage());
        $error = Hash::get($e->getErrors(), $errorFieldName);
        $this->assertNotNull($error, "Expected error not found : {$errorFieldName}. Errors: " . json_encode($e->getErrors()));
    }

    private function insertFixture_UpdateSecretsError_AddSecrets_ValidationExceptions_UserWithoutAccess()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);

        return [$resource1, $userAId];
    }

    public function testUpdateSecretsError_AddSecrets_ValidationExceptions_SoftDeletedUser()
    {
        list($resource1, $userAId) = $this->insertFixture_UpdateSecretsError_AddSecrets_ValidationExceptions_SoftDeletedUser();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $userBId = UuidFactory::uuid('user.id.sofia');
        $data = [['user_id' => $userBId, 'data' => Hash::get(self::getDummySecretData(), 'data')], ];

        try {
            $this->service->updateSecrets($uac, $resource1->id, $data);
            $this->assertFalse(true, 'The test should catch an exception');
        } catch (CustomValidationException $e) {
            $this->assertUpdateSecretsValidationException($e, '0.user_id.user_is_not_soft_deleted');
        }
    }

    private function insertFixture_UpdateSecretsError_AddSecrets_ValidationExceptions_SoftDeletedUser()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);

        return [$resource1, $userAId];
    }

    /* ************************************************************** */
    /* DELETE SECRETS */
    /* ************************************************************** */

    public function testUpdateSecretsSuccess_DeleteSecrets()
    {
        list($resource1, $userAId, $userBId) = $this->insertFixture_UpdateSecretsSuccess_DeleteSecrets();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $data = [];

        $this->service->updateSecrets($uac, $resource1->id, $data);

        // Assert secrets
        $secrets = $this->Secrets->findByResourceId($resource1->id)->toArray();
        $this->assertCount(1, $secrets);
        $this->assertSecretExists($resource1->id, $userAId);
    }

    private function insertFixture_UpdateSecretsSuccess_DeleteSecrets()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $userBId = UuidFactory::uuid('user.id.betty');
        $for = [$userAId => Permission::OWNER, $userBId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);
        // Remove Betty's permission but keep the secret.
        $this->Permissions->deleteAll(['aco_foreign_key' => $resource1->id, 'aro_foreign_key' => $userBId]);

        return [$resource1, $userAId, $userBId];
    }

    /* ************************************************************** */
    /* UPDATE SECRETS */
    /* ************************************************************** */

    public function testUpdateSecretsSuccess_UpdateSecrets()
    {
        list($resource1, $userAId) = $this->insertFixture_UpdateSecretsSuccess_UpdateSecrets();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $encryptedSecret = '-----BEGIN PGP MESSAGE-----

0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000000000000000000000000000000000000000000000
0000000000000000000000/0
=0000
-----END PGP MESSAGE-----';
        $data = [
            ['user_id' => $userAId, 'data' => $encryptedSecret],
        ];

        $this->service->updateSecrets($uac, $resource1->id, $data);

        // Assert secrets
        $secrets = $this->Secrets->findByResourceId($resource1->id)->toArray();
        $this->assertCount(1, $secrets);
        $this->assertSecretExists($resource1->id, $userAId);
        $secret = $this->Secrets->findByResourceIdAndUserId($resource1->id, $userAId)->first();
        $this->assertEquals($encryptedSecret, $secret->data);
    }

    private function insertFixture_UpdateSecretsSuccess_UpdateSecrets()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);

        return [$resource1, $userAId];
    }

    public function testUpdateSecretsError_UpdateSecrets_ValidationExceptions_InvalidGpgMessage()
    {
        list($resource1, $userAId) = $this->insertFixture_UpdateSecretsError_UpdateSecrets_ValidationExceptions_InvalidGpgMessage();

        $uac = new UserAccessControl(Role::USER, $userAId);
        $userBId = UuidFactory::uuid('user.id.sofia');
        $data = [['user_id' => $userBId, 'data' => 'invalid-message']];

        try {
            $this->service->updateSecrets($uac, $resource1->id, $data);
            $this->assertFalse(true, 'The test should catch an exception');
        } catch (CustomValidationException $e) {
            $this->assertUpdateSecretsValidationException($e, '0.data.isValidGpgMessage');
        }
    }

    private function insertFixture_UpdateSecretsError_UpdateSecrets_ValidationExceptions_InvalidGpgMessage()
    {
        $userAId = UuidFactory::uuid('user.id.ada');
        $for = [$userAId => Permission::OWNER];
        $resource1 = $this->addResourceFor(['name' => 'R1'], $for);

        return [$resource1, $userAId];
    }
}
