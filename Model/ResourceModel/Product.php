<?php

namespace Compass\StockIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Product extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('compass_stockintegration_products', 'id');
    }
}