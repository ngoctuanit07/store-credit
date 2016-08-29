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

class AW_Storecredit_Adminhtml_Awstorecredit_Sales_OrderController extends Mage_Adminhtml_Controller_Action
{
    public function saveStoreCreditAction()
    {
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $useStoreCredit = $this->getRequest()->getParam('use_storecredit');
        if ( ! Mage::helper('aw_storecredit')->isModuleOutputEnabled()
            || ! Mage::helper('aw_storecredit/config')->isModuleEnabled()
            || ! $quote
            || ! $quote->getCustomerId()
            || is_null($useStoreCredit)
        ) {
            return;
        }
        if ($useStoreCredit) {
            $storeCredit = Mage::getModel('aw_storecredit/storecredit')->loadByCustomerId($quote->getCustomerId());
            if ($storeCredit) {
                $quote->setStorecreditInstance($storeCredit);
                if (count(Mage::helper('aw_storecredit/totals')->getQuoteStoreCredit($quote->getId())) == 0) {
                    Mage::helper('aw_storecredit/totals')->addStoreCreditToQuote($storeCredit, $quote);
                }
            }
        }
        if ( ! $useStoreCredit
            && count(Mage::helper('aw_storecredit/totals')->getQuoteStoreCredit($quote->getId())) >= 1
        ) {
            $storeCredit = Mage::getModel('aw_storecredit/storecredit')->loadByCustomerId($quote->getCustomerId());
            if ($storeCredit) {
                $quote->setStorecreditInstance(null);
                Mage::helper('aw_storecredit/totals')->removeStoreCreditFromQuote($storeCredit->getEntityId(), $quote);
            }
        }
        return;
    }
}