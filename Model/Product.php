<?php

namespace Compass\StockIntegration\Model;

use Magento\Framework\Model\AbstractModel;

class Product extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Compass\StockIntegration\Model\ResourceModel\Product::class);
    }
}