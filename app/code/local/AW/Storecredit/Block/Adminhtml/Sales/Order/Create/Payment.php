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

class AW_Storecredit_Block_Adminhtml_Sales_Order_Create_Payment extends Mage_Core_Block_Template
{
    /**
     * @var null|AW_Storecredit_Model_Storecredit
     */
    protected $_storecreditModel = null;

    /**
     * @return array
     */
    public function getAwStorecredit()
    {
        $quote = $this->_getOrderCreateModel()->getQuote();
        $storeCredits = Mage::helper('aw_storecredit/totals')->getQuoteStoreCredit($quote->getId());
        if (!is_array($storeCredits)) {
            $storeCredits = array();
        }
        return $storeCredits;
    }

    /**
     * @return $this
     */
    protected function _beforeToHtml()
    {
        if ( ! $this->isAllowed()) {
            return $this;
        }
        $this->_getOrderCreateModel()->collectRates();
        $billingMethodsForm = $this->getParentBlock()->getChild('form');
        $methods = $billingMethodsForm->getMethods();
        foreach ($methods as $key => $method) {
            if ( ! ($this->getQuote()->getBaseGrandTotal() > 0)
                && 'free' != $method->getCode()
            ) {
                unset($methods[$key]);
            }
        }
        $billingMethodsForm->setData('methods', $methods);
        return $this;
    }

    /**
     * @return string
     */
    public function getUrlStoreCreditSave()
    {
        return Mage::helper("adminhtml")->getUrl('adminhtml/awstorecredit_sales_order/saveStoreCredit');
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_getOrderCreateModel()->getQuote();
    }

    /**
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        return $this->getQuote()->getCustomer();
    }

    /**
     * @return bool
     */
    public function isDisplayContainer()
    {
        if ( ! Mage::helper('aw_storecredit/config')->isModuleEnabled()) {
            return false;
        }
        if ( ! $this->getCustomer()->getId()) {
            return false;
        }
        if ($this->getBalance() <= 0) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function isAllowed()
    {
        if ( ! $this->isDisplayContainer()) {
            return false;
        }
        if ( ! ((float) $this->getAmountToCharge() > 0) &&  ! $this->isStorecreditUsed()) {
            return false;
        }
        return true;
    }

    /**
     * @return int
     */
    public function getBalance()
    {
        if ( ! $this->getCustomer()->getId()) {
            return 0;
        }
        return $this->_getStorecreditModel()->getBalance();
    }

    /**
     * @return mixed(int|string|float)
     */
    public function getAmountToCharge()
    {
        if ($this->isStorecreditUsed()) {
            return $this->getQuote()->getAwStorecreditAmountUsed();
        }
        return min($this->getBalance(), $this->getQuote()->getBaseGrandTotal());
    }

    /**
     * @return mixed(int|string|float)
     */
    public function isStorecreditUsed() {
        return $this->getQuote()->getAwStorecreditAmountUsed();
    }

    /**
     * @return Mage_Adminhtml_Model_Sales_Order_Create
     */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
     * @return AW_Storecredit_Model_Storecredit
     */
    protected function _getStorecreditModel()
    {
        if (is_null($this->_storecreditModel)) {
            $this->_storecreditModel = Mage::getModel('aw_storecredit/storecredit');

            if ($this->getCustomer()->getId()) {
                $this->_storecreditModel->loadByCustomerId($this->getCustomer()->getId());
            }
        }
        return $this->_storecreditModel;
    }

    /**
     * @return string
     */
    public function getFormattedBalance()
    {
        $baseBalance = $this->getBalance();
        $storeId = $this->getQuote()->getStoreId();
        $balance = Mage::helper('core')->currencyByStore($baseBalance, $storeId, true);
        return $balance;
    }
}