<?php

declare(strict_types=1);

namespace OCA\FileRequests\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1200Date20260413000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('filereq_requests');

        if (!$table->hasColumn('department')) {
            $table->addColumn('department', Types::STRING, [
                'notnull' => false,
                'length' => 256,
                'default' => null,
            ]);
        }

        if (!$table->hasColumn('province')) {
            $table->addColumn('province', Types::STRING, [
                'notnull' => false,
                'length' => 256,
                'default' => null,
            ]);
        }

        if (!$table->hasColumn('municipality_city')) {
            $table->addColumn('municipality_city', Types::STRING, [
                'notnull' => false,
                'length' => 256,
                'default' => null,
            ]);
        }

        if (!$table->hasColumn('project')) {
            $table->addColumn('project', Types::STRING, [
                'notnull' => false,
                'length' => 256,
                'default' => null,
            ]);
        }

        if (!$table->hasColumn('permit_document_name')) {
            $table->addColumn('permit_document_name', Types::STRING, [
                'notnull' => false,
                'length' => 256,
                'default' => null,
            ]);
        }

        if (!$table->hasColumn('date_needed')) {
            $table->addColumn('date_needed', Types::STRING, [
                'notnull' => false,
                'length' => 10,
                'default' => null,
            ]);
        }

        return $schema;
    }
}
