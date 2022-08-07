<?php

namespace Compass\StockIntegration\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\File\Csv;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

use Compass\StockIntegration\Model\ProductFactory;

class Data extends AbstractHelper
{
    const indexIds = ['cataloginventory_stock','inventory'];
    
    const CONFIG_GENERAL_ENABLED = 'general/enable';    
    const CONFIG_IMPORT_FILE_PATH = 'import/filepath';
    const CONFIG_IMPORT_DELIMITER = 'import/delimiter';
    const CONFIG_IMPORT_SEPERATOR = 'import/seperator';
    const CONFIG_CRON_ENABLED = 'cron/enable';  
    
    protected $_resourceConnection;    
    protected $_directoryList;
    protected $_driverFile;    
    protected $_ioFile;
    protected $_csv;
    protected $_dateTime;       
    protected $_indexerFactory;
    protected $_historyFactory;
    protected $_importDirectory;    
    protected $_logger;
    
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,   
        DirectoryList $_directoryList,
        Filesystem $fileSystem,    
        DriverFile $driverFile,        
        IoFile $ioFile,
        Csv $csv,
        DateTime $dateTime,
        IndexerFactory $indexerFactory,
        ProductFactory $historyFactory,
        LoggerInterface $logger
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->_directoryList =  $_directoryList;
        $this->_driverFile = $driverFile;        
        $this->_ioFile = $ioFile;
        $this->_csv = $csv;
        $this->_dateTime = $dateTime;
        $this->_indexerFactory = $indexerFactory;
        $this->_historyFactory = $historyFactory;
        $this->_importDirectory = $fileSystem->getDirectoryWrite(DirectoryList::VAR_DIR);        
        $this->_logger = $logger;
        
        parent::__construct($context);        
    }
    
    public function getModuleConfig($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            'compass_stockintegration/' . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    public function isEnabled() {
        return (bool)$this->getModuleConfig(self::CONFIG_GENERAL_ENABLED);
    }
    
    public function isCronEnabled() {
        return $this->isEnabled() && (bool)$this->getModuleConfig(self::CONFIG_CRON_ENABLED);
    }
    
    protected function getVarDirectory()
    {
        return $this->_directoryList->getPath(DirectoryList::VAR_DIR);
    }    
    
    protected function isImportDirectory()
    {
        return $this->_importDirectory->isDirectory($this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH));
    }
    
    protected function getImportDirectory()
    {
        return $this->_directoryList->getPath(DirectoryList::VAR_DIR) . $this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH);
    }
    
    protected function isArchivedDirectory()
    {
        return $this->_importDirectory->isDirectory($this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH) . "/archived");
    }
    
    protected function createProcessedDirectory()
    {
        try {
            
            $this->_importDirectory->create($this->getVarDirectory() . $this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH) . "/archived");
            
            return true;
            
        } catch (FileSystemException $e) {
            throw new LocalizedException(
                __('We can\'t create directory %1', self::CONFIG_IMPORT_FILE_PATH . "/archived")
            );
        }
    }
    
    protected function moveImportedFileArchivedDir($file)
    {
        try {
            
            if(!$this->isArchivedDirectory()) {
                $this->createProcessedDirectory();
            }
            
            $this->_ioFile->mv($this->getVarDirectory() . $this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH) . $file,$this->getVarDirectory() . $this->getModuleConfig(self::CONFIG_IMPORT_FILE_PATH) . "/archived/" . date("YmdHi") . "_" . $file);
            
            return true;
            
        } catch (FileSystemException $e) {
            throw new LocalizedException(
                __('We can\'t move %1 to directory', $file)
            );
        }
    }
    
    protected function reindex() {

        foreach (self::indexIds as $indexerId) {
            try {            
                $indexer = $this->_indexerFactory->create()->load($indexerId);
                $indexer->reindexRow($indexerId);
            } catch(\Exception $e) {
                throw new LocalizedException(
                    $e->getMessage()
                );            
            }
        }
    }
    
    protected function getStock($sku) {

        $connection = $this->_resourceConnection->getConnection();        

        $catalogProductEntityTable = $connection->getTableName('catalog_product_entity');
        $catalogInventoryStockItemTable = $connection->getTableName('cataloginventory_stock_item');

        $query = "SELECT * FROM " . $catalogInventoryStockItemTable . " as ci INNER JOIN " . $catalogProductEntityTable . " as e ON ci.product_id = e.entity_id WHERE e.sku = '" . $sku . "'";

        $validateData = $connection->fetchRow($query);

        if($validateData) {
            return $validateData['qty'];
        }    
        
        return;
    }
    
    protected function updateStock($data) {
        
        try {
            
            $beforeQuantity = $this->getStock($data['sku']) ? $this->getStock($data['sku']) : 0;
            
            $connection = $this->_resourceConnection->getConnection();        

            $catalogProductEntityTable = $connection->getTableName('catalog_product_entity');
            $catalogInventoryStockItemTable = $connection->getTableName('cataloginventory_stock_item');

            $query = "UPDATE " . $catalogInventoryStockItemTable . " as ci INNER JOIN " . $catalogProductEntityTable . " as e ON ci.product_id = e.entity_id SET qty = " . $data['qty'] . ", is_in_stock = " . ($data['qty'] > 0 ? 1 : 0) . " WHERE e.sku = '" . $data['sku'] . "'";

            $connection->query($query);
                        
            return ['result' => 'success', 'before_quantity' => $beforeQuantity, 'message' => null];
            
        } catch (\Exception $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function run() {
        
        try {     

            if($this->isImportDirectory()) {

                $csvFiles =  $this->_driverFile->readDirectory($this->getImportDirectory());

                if($csvFiles) {
                    
                    $i=0;
                    $successMessages = [];
                    $errorMessages = [];
                    $runIndex = false;
                    
                    foreach($csvFiles as $csvFile) {

                        if(!$this->_driverFile->isFile($csvFile)) continue;
                        
                        $this->_csv->setDelimiter($this->getModuleConfig(self::CONFIG_IMPORT_DELIMITER));
                        $datas = $this->_csv->getData($csvFile);
                        
                        if (!empty($datas)) {

                            $success = 0;
                            $error = 0;
                            foreach ($datas as $key => $value) {
                                
                                $row = explode($this->getModuleConfig(self::CONFIG_IMPORT_SEPERATOR),trim($value['0']));
                                
                                if($row) {
                                    
                                    $sku = isset($row[0]) ? $row[0] : null;
                                    $qty = isset($row[1]) ? $row[1] : null;
                                    
                                    $response = $this->updateStock(['sku' => $sku, 'qty' => $qty]);

                                    if($response) {
                                        
                                        $history = $this->_historyFactory->create()->load($sku,'sku');

                                        if($response['result'] == 'success') {                                    
                                            $historyData = [
                                                'sku' => $sku,
                                                'before_quantity' => $response['before_quantity'],
                                                'after_quantity' => $qty,
                                                'message' => null,
                                                'created_at' => date("Y-m-d H:i:s")
                                            ];
                                            $success++;

                                        } else {
                                            $historyData = [
                                                'sku' => $sku,
                                                'message' => $response['message'],
                                                'created_at' => date("Y-m-d H:i:s")
                                            ];
                                            $error++;
                                        }
                                        
                                        if($history
                                          && $history->getId()) {
                                            $history->addData($historyData);
                                        } else {
                                            $history->setData($historyData);
                                        }
                                        $history->save();                                        
                                    }
                                }
                            }
                            
                            $this->moveImportedFileArchivedDir(basename($csvFile));
                            $runIndex = true;
                            $successMessages[] = __('%1 file executed. %2 record(s) have been updated successfully. %3 record(s) failed.',basename($csvFile),$success,$error);
                            $i++;
                            
                        } else {
                            $message = __('There is no data into %1',basename($csvFile));
                            $errorMessages[] = $message;
                            $this->_logger->error($message);                              
                        }                    
                    }
                                       
                    if($runIndex > 0) {
                        $this->reindex();
                    }
                    
                    if($i < 1) {
                        $message = __('The file to import could not be found');
                        $this->_logger->error($message); 
                        return ['error_message' => $message];
                    }

                    return ['success_message' => $successMessages , 'error_message' => $errorMessages];
                    
                } else {
                    $message = __('The file to import could not be found');
                    $this->_logger->error($message);   
                    return ['error_message' => $messsage];
                }

            } else {
                $messsage = __('We can\'t get directory %1', self::CONFIG_IMPORT_FILE_PATH);
                $this->_logger->error($messsage);
                return ['error_message' => $messsage];
            }

        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
            return ['error_message' => $e->getMessage()];
        }
    }
}