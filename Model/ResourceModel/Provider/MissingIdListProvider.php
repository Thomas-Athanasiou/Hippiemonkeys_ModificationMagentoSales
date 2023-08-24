<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2023 Hippiemonkeys Web Intelligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_ModificationMagentoSales
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\ModificationMagentoSales\Model\ResourceModel\Provider;

    use Magento\Framework\App\ResourceConnection,
        Magento\Framework\DB\Adapter\AdapterInterface,
        Magento\Sales\Model\ResourceModel\Provider\NotSyncedDataProviderInterface,
        Hippiemonkeys\Core\Api\Helper\ConfigInterface;

    class MissingIdListProvider
    implements NotSyncedDataProviderInterface
    {
        protected const CONNECTION_NAME = 'sales';

        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Framework\App\ResourceConnection $resourceConnection
         * @param \Hippiemonkeys\Core\Api\Helper\ConfigInterface $config
         */
        public function __construct(
            ResourceConnection $resourceConnection,
            ConfigInterface $config
        )
        {
            $this->_connection = $resourceConnection->getConnection(self::CONNECTION_NAME);
            $this->_resourceConnection = $resourceConnection;
            $this->_config = $config;
        }

        /**
         * @inheritdoc
         */
        public function getIds($mainTableName, $gridTableName)
        {
            $ids = [];

            if($this->getIsActive())
            {
                $resourceConnection = $this->getResourceConnection();
                $connection = $this->getConnection();

                $ids = $connection->fetchAll(
                    $connection->select()
                        ->from(['main_table' => $resourceConnection->getTableName($mainTableName)], ['main_table.entity_id'])
                        ->joinLeft(
                            ['grid_table' => $resourceConnection->getTableName($gridTableName)],
                            'main_table.entity_id = grid_table.entity_id',
                            []
                        )
                        ->where("grid_table.entity_id IS NULL"),
                    [],
                    \Zend_Db::FETCH_COLUMN
                );
            }

            return $ids;
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
         * Connection property
         *
         * @access private
         *
         * @var \Magento\Framework\DB\Adapter\AdapterInterface $_connection
         */
        private $_connection;

        /**
         * Gets Connection
         *
         * @access protected
         *
         * @return \Magento\Framework\DB\Adapter\AdapterInterface
         */
        protected function getConnection(): AdapterInterface
        {
            return $this->_connection;
        }

        /**
         * Resource Connection property
         *
         * @access private
         *
         * @var \Magento\Framework\App\ResourceConnection $_resourceConnection
         */
        private $_resourceConnection;

        /**
         * Gets Resource Connection
         *
         * @access protected
         *
         * @return \Magento\Framework\App\ResourceConnection
         */
        protected function getResourceConnection(): ResourceConnection
        {
            return $this->_resourceConnection;
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