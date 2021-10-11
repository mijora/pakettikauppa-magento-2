<?php

namespace Pakettikauppa\Logistics\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Pakettikauppa\Logistics\Block\Adminhtml\Form\Field\YesNo;
use Pakettikauppa\Logistics\Block\Adminhtml\Form\Field\Name;
use Pakettikauppa\Logistics\Helper\Api;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Ranges
 */
class Carriers extends AbstractFieldArray {

    private $enabledRenderer;
    private $nameRenderer;
    protected $_template = 'Pakettikauppa_Logistics::system/config/form/field/array.phtml';
    private $apiHelper;
    private $scopeConfig;
    private $configWriter;
    private $_arrayRowsCache;

    public function __construct(\Magento\Backend\Block\Template\Context $context,
            Api $apiHelper,
            ScopeConfigInterface $scopeConfig,
            WriterInterface $configWriter,
            array $data = []) {
        $this->apiHelper = $apiHelper;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        parent::__construct($context, $data);
        //$this->refreshShippingServices(); exit;
    }

    private function refreshShippingServices() {
        $methods = $this->apiHelper->getHomeDelivery(true);
        
        //var_dump($methods); exit;
        $items = json_decode($this->scopeConfig->getValue('carriers/pakettikauppa/pakettikauppa_carriers', \Magento\Store\Model\ScopeInterface::SCOPE_STORE), true);
        if (!is_array($items) || empty($items)) {
            $items = [];
        }
        //in case no methods returned
        if (!count($methods)) {
            return $items;
        }
        $methods_items = [];
        $methods_found = [];
        $new_config = [];
        foreach ($methods as $method) {
            $methods_items[$method->shipping_method_code] = $method;
        }
        foreach ($items as $key => $item) {
            if (isset($item['code']) && isset($methods_items[$item['code']])) {
                $methods_found[$item['code']] = $item['code'];
                $new_config[$key] = $item;
                $new_config[$key]['name'] = $methods_items[$item['code']]->service_provider . ' - ' . $methods_items[$item['code']]->name;
            }
        }
        $main_key = '_' . number_format(microtime(true),4,'_','');
        $counter = 1;
        foreach ($methods_items as $code => $item) {
            if (isset($methods_found[$code])) {
                continue;
            }
            $methods_found[$code] = $code;
            $key = $main_key . $counter;
            $new_config[$key] = [];
            $new_config[$key]['name'] = $item->service_provider . ' - ' . $item->name;
            $new_config[$key]['code'] = $code;
            $new_config[$key]['provider'] = $item->service_provider;
            $new_config[$key]['is_pickup_service'] = $item->has_pickup_points ? 1: 0;
            $new_config[$key]['enabled'] = 0;
            $new_config[$key]['price'] = 5;
            $new_config[$key]['min_cart'] = 0;
            $new_config[$key]['discount_price'] = 5;
            $counter++;
        }
        
        usort($new_config, [$this, 'sortByName']);
        return $new_config;
        //$this->configWriter->save('carriers/pakettikauppa/pakettikauppa_carriers',  json_encode($new_config), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
    }
    
    private function sortByName($a, $b){
        return $a['name'] > $b['name'];
    }

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender() {
        $this->addColumn('name', ['label' => __('Name'), 'class' => 'required-entry']);
        $this->addColumn('enabled', ['label' => __('Enabled'), 'class' => 'required-entry', 'renderer' => $this->getEnabledRenderer()]);
        $this->addColumn('price', ['label' => __('Price'), 'class' => 'required-entry']);
        $this->addColumn('min_cart', ['label' => __('Min discount sum'), 'class' => 'required-entry']);
        $this->addColumn('discount_price', ['label' => __('Discount price'), 'class' => 'required-entry']);
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void {
        $options = [];


        $row->setData('option_extra_attrs', $options);
    }

    public function getArrayRows() {
        if (null !== $this->_arrayRowsCache) {
            return $this->_arrayRowsCache;
        }
        $result = [];
        /** @var \Magento\Framework\Data\Form\Element\AbstractElement */
        $data = $this->refreshShippingServices();
        foreach ($data as $rowId => $row) {
            $rowColumnValues = [];
            foreach ($row as $key => $value) {
                $row[$key] = $value;
                $rowColumnValues[$this->_getCellInputElementId($rowId, $key)] = $row[$key];
            }
            $row['_id'] = $rowId;
            $row['column_values'] = $rowColumnValues;
            $result[$rowId] = new \Magento\Framework\DataObject($row);
            $this->_prepareArrayRow($result[$rowId]);
        }

        $this->_arrayRowsCache = $result;
        return $this->_arrayRowsCache;
    }

    public function renderCellTemplate($columnName) {
        $options = [];
        if ($columnName == 'name' && isset($this->_columns[$columnName])) {
            return '<%- name %>'
            . '<input type="hidden" id="<%- _id %>_name" name="groups[pakettikauppa][fields][pakettikauppa_carriers][value][<%- _id %>][name]" value="<%- name %>"/>'
            . '<input type="hidden" id="<%- _id %>_provider" name="groups[pakettikauppa][fields][pakettikauppa_carriers][value][<%- _id %>][provider]" value="<%- provider %>"/>'
            . '<input type="hidden" id="<%- _id %>_is_pickup_service" name="groups[pakettikauppa][fields][pakettikauppa_carriers][value][<%- _id %>][is_pickup_service]" value="<%- is_pickup_service %>"/>'
            . '<input type="hidden" id="<%- _id %>_code" name="groups[pakettikauppa][fields][pakettikauppa_carriers][value][<%- _id %>][code]" value="<%- code %>"/>';
        }
        return parent::renderCellTemplate($columnName);
    }

    private function getEnabledRenderer() {
        if (!$this->enabledRenderer) {
            $this->enabledRenderer = $this->getLayout()->createBlock(
                    YesNo::class,
                    '',
                    ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->enabledRenderer;
    }

    private function getNameRenderer() {
        if (!$this->nameRenderer) {
            $this->nameRenderer = $this->getLayout()->createBlock(
                    Name::class,
                    '',
                    ['data' => ['is_render_to_js_template' => false]]
            );
        }
        return $this->nameRenderer;
    }

}
