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

class AW_Storecredit_Adminhtml_Awstorecredit_ImportController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/aw_storecredit/import');
    }

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/transactions');

        $this
            ->_title($this->__('Sales'))
            ->_title($this->__('Store Credit'))
        ;
        return $this;
    }

    public function indexAction()
    {
        $this->_forward('edit');
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $this->_initAction();
        $this->_title($this->__('Import'));
        $this->renderLayout();
    }

    public function saveAction()
    {
        $actionIfCustomerExist = Mage::app()->getRequest()->getPost('action_if_customer_exist', 1);
        $import = Mage::getModel('aw_storecredit/import');

        try {
            $import->uploadFile();

            $result = $import->importFromFile($actionIfCustomerExist);
            if($result['status']){
                Mage::getSingleton('adminhtml/session')->addSuccess($result['message']);
            }else{
                Mage::getSingleton('adminhtml/session')->addError($result['message']);
            }
        }catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }
}