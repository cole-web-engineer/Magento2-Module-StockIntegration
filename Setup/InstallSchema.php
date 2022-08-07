<?php

namespace Compass\StockIntegration\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        $table = $installer->getConnection()
            ->newTable($installer->getTable('compass_stockintegration_products'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]
            )
            ->addColumn(
                'sku',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false]
            )
            ->addColumn(
                'before_quantity',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                16,
                ['nullable' => false,'default' => 0]
            )  
            ->addColumn(
                'after_quantity',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                16,
                ['nullable' => false,'default' => 0]
            )                 
            ->addColumn(
                'message',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                ['nullable' => true]
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' => false]
            )->setComment('Compass Stock Integration Products');
        
        $installer->getConnection()->createTable($table);       

        $installer->endSetup();
    }
}
