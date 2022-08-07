<?php

namespace Compass\StockIntegration\Ui\DataProvider\Listing;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

use Compass\StockIntegration\Model\ResourceModel\Product;
use Compass\StockIntegration\Model\ResourceModel\Product\CollectionFactory;

class ProductDataProvider extends AbstractDataProvider
{
    private $collectionFactory;

    public function __construct(
        CollectionFactory $collectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->collectionFactory = $collectionFactory;
    }

    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
        }
        
        return $this->collection;
    }
}
