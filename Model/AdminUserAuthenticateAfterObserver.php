<?php
namespace TapLink\BlindHashing\Model;
class AdminUserAuthenticateAfterObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer){
        exit(__FILE__);
    }
}
