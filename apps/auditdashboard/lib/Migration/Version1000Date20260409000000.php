<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260409000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('audit_log')) {
            $table = $schema->createTable('audit_log');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('timestamp', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
                'default' => '',
            ]);
            $table->addColumn('action', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('category', Types::STRING, [
                'notnull' => true,
                'length' => 32,
            ]);
            $table->addColumn('target', Types::STRING, [
                'notnull' => true,
                'length' => 1024,
                'default' => '',
            ]);
            $table->addColumn('details', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('ip_address', Types::STRING, [
                'notnull' => false,
                'length' => 45,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['timestamp'], 'audit_timestamp_idx');
            $table->addIndex(['user_id'], 'audit_user_idx');
            $table->addIndex(['category'], 'audit_category_idx');
            $table->addIndex(['action'], 'audit_action_idx');
        }

        return $schema;
    }
}
