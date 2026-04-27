<?php

declare(strict_types=1);

namespace OCA\FileRequests\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260409100000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('filereq_requests')) {
            $table = $schema->createTable('filereq_requests');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('requester_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('custodian_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 256]);
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'pending']);
            $table->addColumn('reject_reason', Types::TEXT, ['notnull' => false]);
            $table->addColumn('expires_at', Types::DATETIME, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['requester_id'], 'filereq_requester_idx');
            $table->addIndex(['custodian_id'], 'filereq_custodian_idx');
            $table->addIndex(['status'], 'filereq_status_idx');
            $table->addIndex(['expires_at'], 'filereq_expires_idx');
        }

        if (!$schema->hasTable('filereq_fulfillments')) {
            $table = $schema->createTable('filereq_fulfillments');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('request_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('node_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('file_path', Types::STRING, ['notnull' => true, 'length' => 1024]);
            $table->addColumn('file_name', Types::STRING, ['notnull' => true, 'length' => 256]);
            $table->addColumn('share_id', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('share_type', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('share_token', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('password_protected', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
            $table->addColumn('share_expiry', Types::DATETIME, ['notnull' => false]);
            $table->addColumn('permissions', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('locked', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['request_id'], 'filereq_fulfill_req_idx');
        }

        if (!$schema->hasTable('filereq_activity')) {
            $table = $schema->createTable('filereq_activity');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('request_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('message', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['request_id'], 'filereq_activity_req_idx');
        }

        return $schema;
    }
}
