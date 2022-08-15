<?php

namespace Compass\StockIntegration\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\File\Csv;
use Magento\Framework\ObjectManagerInterface;
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
    const CONFIG_HISTORY_ENABLED = 'history/enable';      
     
    protected $_directoryList;
    protected $_driverFile;    
    protected $_ioFile;
    protected $_csv;
    protected $_dateTime;
    protected $_stockRegistry;
    protected $_indexerFactory;
    protected $_historyFactory;
    protected $_importDirectory;    
    protected $_logger;
    
    public function __construct(
        Context $context,
        DirectoryList $_directoryList,
        Filesystem $fileSystem,    
        DriverFile $driverFile,        
        IoFile $ioFile,
        Csv $csv,
        ObjectManagerInterface $objectmanager,
        DateTime $dateTime,
        IndexerFactory $indexerFactory,
        ProductFactory $historyFactory,
        LoggerInterface $logger
    ) {
        $this->_directoryList =  $_directoryList;
        $this->_driverFile = $driverFile;        
        $this->_ioFile = $ioFile;
        $this->_csv = $csv;
        $this->_dateTime = $dateTime;
        $this->_stockRegistry = $objectmanager->create('\Magento\CatalogInventory\Api\StockRegistryInterface');
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
    
    public function isHistoryEnabled() {
        return $this->isEnabled() && (bool)$this->getModuleConfig(self::CONFIG_HISTORY_ENABLED);
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
    
    protected function updateStock($data) {

        try {
         
            $stockItem = $this->_stockRegistry->getStockItemBySku(trim($data['sku']));
         
            $beforeQuantity = 0;
            
            if($stockItem
              && $stockItem->getId()) {
                
                $beforeQuantity = $stockItem->getQty();
                
                $stockItem->setQty($data['qty']);
                $stockItem->setIsInStock((bool)$data['qty']);
                $this->_stockRegistry->updateStockItemBySku($data['sku'], $stockItem);      
                
                return ['result' => 'success', 'before_quantity' => $beforeQuantity, 'message' => null];                
            }            

            return ['result' => 'error', 'message' => __('There is no validated stock item.')];                        
              
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
                                
                                $row = explode($this->getModuleConfig(self::CONFIG_IMPORT_SEPERATOR),$value['0']);
                              
                                if($row) {
                                    
                                    $sku = isset($row[0]) ? $row[0] : null;                                  
                                    $qty = isset($row[1]) ? $row[1] : null;

                                    $response = $this->updateStock(['sku' => $sku, 'qty' => $qty]);

                                    if($response) {
                                        
                                        if($this->isHistoryEnabled()) {
                                            
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
                                            
                                        } else {
                                            
                                            if($response['result'] == 'success') {
                                                $success++;

                                            } else {
                                                $error++;
                                            }                                            
                                        }
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
                    return ['error_message' => $message];
                }

            } else {
                $message = __('We can\'t get directory %1', self::CONFIG_IMPORT_FILE_PATH);
                $this->_logger->error($message);
                return ['error_message' => $message];
            }

        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
            return ['error_message' => $e->getMessage()];
        }
    }
}