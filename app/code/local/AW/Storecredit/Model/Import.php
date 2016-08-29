<?php
/**
 * aheadWorks Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://ecommerce.aheadworks.com/AW-LICENSE.txt
 *
 * =================================================================
 *                 MAGENTO EDITION USAGE NOTICE
 * =================================================================
 * This software is designed to work with Magento community edition and
 * its use on an edition other than specified is prohibited. aheadWorks does not
 * provide extension support in case of incorrect edition use.
 * =================================================================
 *
 * @category   AW
 * @package    AW_Storecredit
 * @version    1.0.5
 * @copyright  Copyright (c) 2010-2012 aheadWorks Co. (http://www.aheadworks.com)
 * @license    http://ecommerce.aheadworks.com/AW-LICENSE.txt
 */

class AW_Storecredit_Model_Import
{
    const COLUMN_COUNT_IN_CSV_FILE = 11;
    protected $_uploadFilePathToImport = null;

    public function uploadFile(){
        $uploadPath = Mage::getBaseDir('var') . DS . 'importexport' . DS ;
        $uploadFileName = 'aw_storecredit.csv';

        $uploader = new Varien_File_Uploader('storecredit_csv');

        $uploader->setAllowedExtensions(array('csv'));
        $uploader->setAllowRenameFiles(false);
        $uploader->setFilesDispersion(false);

        $uploader->save($uploadPath, $uploadFileName);

        $file = $uploader->getUploadedFileName();
        $this->_uploadFilePathToImport = $uploadPath . $file;
    }

    public function importFromFile($actionIfCustomerExist){
        $result = array(
            'status' => true,
            'message' => ''
        );

        $csvObject = new Varien_File_Csv();
        $csvData = $csvObject->getData($this->_uploadFilePathToImport);

        //first line in file - title column
        if (count($csvData) > 1){
            AW_Lib_Helper_Log::start(Mage::helper('aw_storecredit')->__('Start parsing csv file'));

            $importSuccess = 0;
            foreach ($csvData as $rowNumber => $row) {
                //if title column
                if($rowNumber == 0){
                    continue;
                }

                if(count($row) != self::COLUMN_COUNT_IN_CSV_FILE){
                    AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                        ->__('An error occurred while parsing row %s. The number of columns is not equal to %s',
                            $rowNumber+1, self::COLUMN_COUNT_IN_CSV_FILE));
                    continue;
                }

                $resultRowData = array(
                    "customer_id"        => "",
                    "status"             => "",
                    "created_at"         => "",
                    "total_balance"      => "",
                    "total_spent"        => "",
                    "delta_amount"       => "",
                    "subscribe_state"    => ""
                );

                $counter = 0;
                $isSave = true;
                foreach($resultRowData as $key => $value){
                    if(!$isSave)
                        break;

                    $resultStr = $str = trim($row[$counter]);
                    switch($counter){
                        case 0: // customer id field
                            $counter = 1;

                            if(empty($str)){
                                AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                    ->__('Customer ID field is empty in row %s', $rowNumber+1));
                                $isSave = false;
                                break;
                            }

                            $customers = Mage::getModel('customer/customer')
                                ->getCollection()
                                ->addFieldToFilter('entity_id', $str)
                            ;
                            if(!$customers->getSize()){
                                AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                    ->__('Customer with id %s not found in DB from row %s', $str, $rowNumber+1));
                                $isSave = false;
                            }

                            break;
                        case 1: // status id field
                            $counter = 2;
                            $resultStr = "0";
                            break;
                        case 2: // created at field
                            $counter = 7;
                            $resultStr = "";
                            break;
                        case 7: // total balance filed
                        case 8: // total spent filed
                        case 9: // balance field
                            $counter++;
                            try{
                                $resultStr = empty($str) ? 0 : $this->_getMoneyFromStr($str);
                            }catch (Exception $e) {
                                AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                    ->__('An error occurred while parsing row %s. %s', $rowNumber+1, $e->getMessage()));
                                $isSave = false;
                            }
                            break;
                        case 10: // subscribe state field
                            $statusKey = Mage::getModel('aw_storecredit/source_storecredit_subscribe_state')
                                ->getKeyByValue($str);
                            if (null !== $statusKey) {
                                $resultStr = $statusKey;
                            }else{
                                $resultStr = "0";
                                if (Mage::helper('aw_storecredit/config')->isAutoSubscribedCustomers()) {
                                    $resultStr = AW_Storecredit_Model_Source_Storecredit_Subscribe_State::SUBSCRIBED_VALUE;
                                }

                                AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                    ->__('Balance Update Subscription status not found in row %s. The default value was set', $rowNumber+1));
                            }
                            break;
                    }
                    $resultRowData[$key] = $resultStr;
                }

                if($isSave){
                    if($resultRowData['total_balance'] == 0)
                        $resultRowData['total_balance'] = ($resultRowData['total_spent'] + $resultRowData['delta_amount']);

                    $storeCreditCustomers = Mage::getModel('aw_storecredit/storecredit')
                        ->getCollection()
                        ->addFieldToFilter('customer_id', $resultRowData['customer_id'])
                    ;

                    if($storeCreditCustomers->getSize() == 1){
                        $customer = $storeCreditCustomers->getFirstItem();
                        //append balance action
                        if($actionIfCustomerExist == 0){
                            $resultRowData['total_balance'] = $customer->getTotalBalance() + $resultRowData['delta_amount'];
                            $resultRowData['total_spent'] = $resultRowData['total_balance'] - $resultRowData['delta_amount'];
                        }

                        if($this->_balanceValidate($resultRowData)){
                            $storeCreditModel = Mage::getModel('aw_storecredit/storecredit')
                                ->load($customer->getId())
                                ->setTotalBalance($resultRowData['total_balance'])
                                ->setDeltaAmount($resultRowData['delta_amount'])
                                ->setIsImported(true)
                            ;

                            //append balance action
                            if($actionIfCustomerExist == 0){
                                $storeCreditModel->setBalance($customer->getBalance());
                            }else{//replace balance action
                                $storeCreditModel->setBalance(0);
                            }

                            $storeCreditModel->save();
                            $importSuccess++;
                        }else{
                            AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                ->__('Store Credit Balance is not valid in row %s', $rowNumber+1));
                        }
                    }elseif($storeCreditCustomers->getSize() > 1){
                        AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                            ->__('It was found many users with id %s. This user has been ignore in row %s',
                                $resultRowData['customer_id'], $rowNumber+1));
                    }else{
                        if($this->_balanceValidate($resultRowData)){
                            Mage::getModel('aw_storecredit/storecredit')
                                ->addData($resultRowData)
                                ->setIsImported(true)
                                ->save()
                            ;
                            $importSuccess++;
                        }else{
                            AW_Lib_Helper_Log::log(Mage::helper('aw_storecredit')
                                ->__('Store Credit Balance is not valid in row %s', $rowNumber+1));
                        }
                    }
                }
            }
            $result['message'] = Mage::helper('aw_storecredit')->__('Imported %s row(s) out of %s', $importSuccess, count($csvData)-1);
            AW_Lib_Helper_Log::stop(Mage::helper('aw_storecredit')->__('Stop parsing csv file. ').$result['message']);

            if($importSuccess){
                $result['status'] = true;
            }else{
                $result['status'] = false;
            }
        }else{
            $result['status'] = false;
            $result['message'] = Mage::helper('aw_storecredit')->__('Csv file is empty');
        }

        return $result;
    }

    protected function _getMoneyFromStr($str){
        $locale = new Zend_Locale(Mage::app()->getLocale()->getLocaleCode());

        $result = Zend_Locale_Format::getFloat(preg_replace("/[^-0-9\.\,]/", "", $str),
            array(
                'precision' => 2,
                'locale' => $locale
            )
        );

        return $result;
    }

    protected function _balanceValidate($resultRowData){
        return ($resultRowData['total_spent'] + $resultRowData['delta_amount']) == $resultRowData['total_balance'];
    }
}