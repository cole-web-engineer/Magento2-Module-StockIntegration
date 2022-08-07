<?php

namespace Compass\StockIntegration\Model\ResourceModel\Product;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Compass\StockIntegration\Model\Product::class,
            \Compass\StockIntegration\Model\ResourceModel\Product::class
        );
        $this->_setIdFieldName($this->getResource()->getIdFieldName());
    }
}