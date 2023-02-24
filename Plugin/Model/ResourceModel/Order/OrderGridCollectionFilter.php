<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2023 Hippiemonkeys Web Inteligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_ModificationMagentoSales
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\ModificationMagentoSales\Plugin\Model\ResourceModel\Order;

    use Magento\Sales\Plugin\Model\ResourceModel\Order\OrderGridCollectionFilter as ParentOrderGridCollectionFilter,
        Magento\Framework\Stdlib\DateTime\TimezoneInterface,
        Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult,
        Hippiemonkeys\Core\Api\Helper\ConfigInterface;

    class OrderGridCollectionFilter
    extends ParentOrderGridCollectionFilter
    {
        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timeZone
         * @param \Hippiemonkeys\Core\Api\Helper\ConfigInterface $config
         */
        public function __construct(
            TimezoneInterface $timeZone,
            ConfigInterface $config
        )
        {
            parent::__construct($timeZone);
            $this->_timeZone = $timeZone;
            $this->_config = $config;
        }

        /**
        * @inheritdoc
        */
        public function aroundAddFieldToFilter(
            SearchResult $subject,
            \Closure $proceed,
            $field,
            $condition = null
        )
        {
            $proceedResult = null;

            if($this->getIsActive())
            {
                if ($field === 'created_at' || $field === 'order_created_at') {
                    if (is_array($condition)) {
                        foreach ($condition as $key => $value) {
                            $condition[$key] = $this->getTimeZone()->convertConfigTimeToUtc($value);
                        }
                    }
                }

                $proceedResult = $proceed($field, $condition);
            }
            else
            {
                $proceedResult = parent::aroundAddFieldToFilter($subject, $proceed, $field, $condition);
            }

            return $proceedResult;
        }

        /**
        * Gets Active flag
        *
        * @access protected
        *
        * @return bool
        */
        protected function getIsActive(): bool
        {
            return $this->getConfig()->getIsActive();
        }

        /**
        * Time Zone property
        *
        * @access private
        *
        * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface $_timeZone
        */
        private TimezoneInterface $_timeZone;

        /**
         * Gets Time Zone
         *
         * @access protected
         *
         * @return \Magento\Framework\Stdlib\DateTime\TimezoneInterface
         */
        protected function getTimeZone(): TimezoneInterface
        {
            return $this->_timeZone;
        }

        /**
         * Config property
         *
         * @access private
         *
         * @var \Hippiemonkeys\Core\Api\Helper\ConfigInterface $_config
         */
        private $_config;

        /**
         * Gets Config
         *
         * @access protected
         *
         * @return \Hippiemonkeys\Core\Api\Helper\ConfigInterface
         */
        protected function getConfig(): ConfigInterface
        {
            return $this->_config;
        }
    }
?>