<?php

namespace Pakettikauppa\Logistics\Block\Adminhtml\Form\Field;


class Name extends \Magento\Framework\View\Element\AbstractBlock
{

     
    protected function _toHtml()
     {
        return $this->getLabel();
    }
    public function getHtml()
     {
         return $this->toHtml();
     }
}
