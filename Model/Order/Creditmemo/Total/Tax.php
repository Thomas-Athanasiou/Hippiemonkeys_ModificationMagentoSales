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

    namespace Hippiemonkeys\ModificationMagentoSales\Model\Order\Creditmemo\Total;

    use Magento\Framework\App\ObjectManager,
        Magento\Sales\Model\Order\Creditmemo,
        Magento\Sales\Model\Order\Invoice,
        Magento\Sales\Model\ResourceModel\Order\Invoice as ResourceInvoice,
        Magento\Tax\Model\Config as TaxConfig,
        Magento\Sales\Model\Order\Creditmemo\Total\Tax as ParentTax,
        Magento\Tax\Model\Calculation as TaxCalculation,
        Hippiemonkeys\Core\Api\Helper\ConfigInterface;

    /**
     * Collects credit memo taxes.
     */
    class Tax
    extends ParentTax
    {
        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Sales\Model\ResourceModel\Order\Invoice $resourceInvoice
         * @param \Hippiemonkeys\Core\Api\Helper\ConfigInterface $config
         * @param array $data
         * @param \Magento\Tax\Model\Config|null $taxConfig
         */
        public function __construct(
            ResourceInvoice $resourceInvoice,
            ConfigInterface $config,
            array $data = [],
            ?TaxConfig $taxConfig = null
        )
        {
            parent::__construct($resourceInvoice, $data, $taxConfig);
            $this->_resourceInvoice = $resourceInvoice;
            $this->_config = $config;
            $this->_taxConfig = $taxConfig ?: ObjectManager::getInstance()->get(TaxConfig::class);
        }

        /**
         * {@inheritdoc}
         */
        public function collect(Creditmemo $creditmemo)
        {
            return $this->getIsActive() ? $this->modifiedCollect($creditmemo) : parent::collect($creditmemo);
        }

        /**
         * Modified Version of Collect
         *
         * @access protected
         *
         * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
         *
         * @return \Hippiemonkeys\ModificationMagentoSales\Model\Order\Creditmemo\Total\Tax
         */
        protected function modifiedCollect(Creditmemo $creditmemo): Tax
        {
            $shippingTaxAmount = 0;
            $baseShippingTaxAmount = 0;
            $totalTax = 0;
            $baseTotalTax = 0;
            $totalDiscountTaxCompensation = 0;
            $baseTotalDiscountTaxCompensation = 0;
            $order = $creditmemo->getOrder();

            foreach ($creditmemo->getAllItems() as $item)
            {
                $orderItem = $item->getOrderItem();
                if ($orderItem->isDummy() || $item->getQty() <= 0)
                {
                    continue;
                }

                $orderItemTax = (double) $orderItem->getTaxInvoiced();
                $baseOrderItemTax = (double) $orderItem->getBaseTaxInvoiced();
                $orderItemQty = (double) $orderItem->getQtyInvoiced();

                if ($orderItemQty)
                {
                    /** Check item tax amount */
                    $tax = $orderItemTax - $orderItem->getTaxRefunded();
                    $baseTax = $baseOrderItemTax - $orderItem->getBaseTaxRefunded();
                    $discountTaxCompensation = $orderItem->getDiscountTaxCompensationInvoiced() - $orderItem->getDiscountTaxCompensationRefunded();
                    $baseDiscountTaxCompensation = $orderItem->getBaseDiscountTaxCompensationInvoiced() - $orderItem->getBaseDiscountTaxCompensationRefunded();
                    if (!$item->isLast())
                    {
                        $availableQty = $orderItemQty - $orderItem->getQtyRefunded();
                        $tax = $creditmemo->roundPrice($tax / $availableQty * $item->getQty());
                        $baseTax = $creditmemo->roundPrice(($baseTax / $availableQty * $item->getQty()), 'base');
                        $discountTaxCompensation = $creditmemo->roundPrice($discountTaxCompensation / $availableQty * $item->getQty());
                        $baseDiscountTaxCompensation = $creditmemo->roundPrice($baseDiscountTaxCompensation / $availableQty * $item->getQty(), 'base');
                    }

                    $item->setTaxAmount($tax);
                    $item->setBaseTaxAmount($baseTax);
                    $item->setDiscountTaxCompensationAmount($discountTaxCompensation);
                    $item->setBaseDiscountTaxCompensationAmount($baseDiscountTaxCompensation);

                    $totalTax += $tax;
                    $baseTotalTax += $baseTax;
                    $totalDiscountTaxCompensation += $discountTaxCompensation;
                    $baseTotalDiscountTaxCompensation += $baseDiscountTaxCompensation;
                }
            }

            $isPartialShippingRefunded = false;
            $baseOrderShippingAmount = (float)$order->getBaseShippingAmount();
            if ($invoice = $creditmemo->getInvoice())
            {
                // recalculate tax amounts in case if refund shipping value was changed
                if ($baseOrderShippingAmount && $creditmemo->getBaseShippingAmount() !== null)
                {
                    $taxFactor = $creditmemo->getBaseShippingAmount() / $baseOrderShippingAmount;
                    $shippingTaxAmount = $invoice->getShippingTaxAmount() * $taxFactor;
                    $baseShippingTaxAmount = $invoice->getBaseShippingTaxAmount() * $taxFactor;
                    $totalDiscountTaxCompensation += $invoice->getShippingDiscountTaxCompensationAmount() * $taxFactor;
                    $baseTotalDiscountTaxCompensation += $invoice->getBaseShippingDiscountTaxCompensationAmnt() * $taxFactor;
                    $shippingTaxAmount = $creditmemo->roundPrice($shippingTaxAmount);
                    $baseShippingTaxAmount = $creditmemo->roundPrice($baseShippingTaxAmount, 'base');
                    $totalDiscountTaxCompensation = $creditmemo->roundPrice($totalDiscountTaxCompensation);
                    $baseTotalDiscountTaxCompensation = $creditmemo->roundPrice($baseTotalDiscountTaxCompensation, 'base');
                    if ($taxFactor < 1 && $invoice->getShippingTaxAmount() > 0 || ($order->getShippingDiscountAmount() >= $order->getShippingAmount()))
                    {
                        $isPartialShippingRefunded = true;
                    }
                    $totalTax += $shippingTaxAmount;
                    $baseTotalTax += $baseShippingTaxAmount;
                }
            }
            else
            {
                $orderShippingAmount = (double) $order->getShippingAmount();
                $baseOrderShippingRefundedAmount = (double) $order->getBaseShippingRefunded();
                $shippingTaxAmount = 0;
                $baseShippingTaxAmount = 0;
                $shippingDiscountTaxCompensationAmount = 0;
                $baseShippingDiscountTaxCompensationAmount = 0;
                $shippingDelta = $baseOrderShippingAmount - $baseOrderShippingRefundedAmount;

                $storeId = $order->getStoreId();
                if ($shippingDelta > $creditmemo->getBaseShippingAmount() || $this->isShippingIncludeTaxWithTaxAfterDiscount(is_numeric($storeId) ? ((int) $storeId) : null))
                {
                    $creditmemoShippingAmount = (double) $creditmemo->getShippingAmount();
                    $creditmemoBaseShippingAmount = (double) $creditmemo->getShippingAmount();

                    $part = $orderShippingAmount !== 0.0 ? ($creditmemoShippingAmount / $orderShippingAmount) : $creditmemoShippingAmount;
                    $basePart = $baseOrderShippingAmount !== 0.0 ? ($creditmemoBaseShippingAmount / $baseOrderShippingAmount) : $creditmemoBaseShippingAmount;
                    $shippingTaxAmount = $order->getShippingTaxAmount() * $part;
                    $baseShippingTaxAmount = $order->getBaseShippingTaxAmount() * $basePart;
                    $shippingDiscountTaxCompensationAmount = $order->getShippingDiscountTaxCompensationAmount() * $part;
                    $baseShippingDiscountTaxCompensationAmount = $order->getBaseShippingDiscountTaxCompensationAmnt() * $basePart;
                    $shippingTaxAmount = $creditmemo->roundPrice($shippingTaxAmount);
                    $baseShippingTaxAmount = $creditmemo->roundPrice($baseShippingTaxAmount, 'base');
                    $shippingDiscountTaxCompensationAmount = $creditmemo->roundPrice($shippingDiscountTaxCompensationAmount);
                    $baseShippingDiscountTaxCompensationAmount = $creditmemo->roundPrice($baseShippingDiscountTaxCompensationAmount, 'base');
                    if ($part < 1 && ($order->getShippingTaxAmount() > 0 || ($order->getShippingDiscountAmount() >= $order->getShippingAmount())))
                    {
                        $isPartialShippingRefunded = true;
                    }
                }
                elseif ($shippingDelta == $creditmemo->getBaseShippingAmount())
                {
                    $shippingTaxAmount = $order->getShippingTaxAmount() - $order->getShippingTaxRefunded();
                    $baseShippingTaxAmount = $order->getBaseShippingTaxAmount() - $order->getBaseShippingTaxRefunded();
                    $shippingDiscountTaxCompensationAmount = $order->getShippingDiscountTaxCompensationAmount() - $order->getShippingDiscountTaxCompensationRefunded();
                    $baseShippingDiscountTaxCompensationAmount = $order->getBaseShippingDiscountTaxCompensationAmnt() - $order->getBaseShippingDiscountTaxCompensationRefunded();
                }

                $totalTax += $shippingTaxAmount;
                $baseTotalTax += $baseShippingTaxAmount;
                $totalDiscountTaxCompensation += $shippingDiscountTaxCompensationAmount;
                $baseTotalDiscountTaxCompensation += $baseShippingDiscountTaxCompensationAmount;
            }

            $allowedTax = $this->calculateAllowedTax($creditmemo);
            $allowedBaseTax = $this->calculateAllowedBaseTax($creditmemo);
            $allowedDiscountTaxCompensation = $this->calculateAllowedDiscountTaxCompensation($creditmemo);
            $allowedBaseDiscountTaxCompensation = $this->calculateAllowedBaseDiscountTaxCompensation($creditmemo);

            if ($creditmemo->isLast() && !$isPartialShippingRefunded)
            {
                $totalTax = $allowedTax;
                $baseTotalTax = $allowedBaseTax;
                $totalDiscountTaxCompensation = $allowedDiscountTaxCompensation;
                $baseTotalDiscountTaxCompensation = $allowedBaseDiscountTaxCompensation;
            }
            else
            {
                $totalTax = min($allowedTax, $totalTax);
                $baseTotalTax = min($allowedBaseTax, $baseTotalTax);
                $totalDiscountTaxCompensation = min($allowedDiscountTaxCompensation, $totalDiscountTaxCompensation);
                $baseTotalDiscountTaxCompensation = min($allowedBaseDiscountTaxCompensation, $baseTotalDiscountTaxCompensation);
            }

            $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $totalTax);
            $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseTotalTax);
            $creditmemo->setDiscountTaxCompensationAmount($totalDiscountTaxCompensation);
            $creditmemo->setBaseDiscountTaxCompensationAmount($baseTotalDiscountTaxCompensation);
            $creditmemo->setShippingTaxAmount($shippingTaxAmount);
            $creditmemo->setBaseShippingTaxAmount($baseShippingTaxAmount);
            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalTax + $totalDiscountTaxCompensation);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalTax + $baseTotalDiscountTaxCompensation);

            return $this;
        }

        /**
         * Checks if shipping provided incl tax, tax applied after discount, and discount applied on shipping excl tax
         *
         * @access private
         *
         * @param int|null $storeId
         *
         * @return bool
         */
        private function isShippingIncludeTaxWithTaxAfterDiscount(?int $storeId): bool
        {
            $taxConfig = $this->getTaxConfig();
            $calculationSequence = $taxConfig->getCalculationSequence($storeId);
            return ($calculationSequence === TaxCalculation::CALC_TAX_AFTER_DISCOUNT_ON_EXCL
                || $calculationSequence === TaxCalculation::CALC_TAX_AFTER_DISCOUNT_ON_INCL)
                && $taxConfig->displaySalesShippingInclTax($storeId);
        }

        /**
         * Calculate allowed to Credit Memo tax amount
         *
         * @access private
         *
         * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
         *
         * @return float
         */
        private function calculateAllowedTax(Creditmemo $creditMemo): float
        {
            $invoice = $creditMemo->getInvoice();
            $order = $creditMemo->getOrder();

            if ($invoice !== null)
            {
                $amount = $invoice->getTaxAmount() - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::TAX_AMOUNT);
            }
            else
            {
                $amount = $order->getTaxInvoiced() - $order->getTaxRefunded();
            }

            return (float) $amount - $creditMemo->getTaxAmount();
        }

        /**
         * Calculate allowed to Credit Memo tax amount in the base currency
         *
         * @access private
         *
         * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
         *
         * @return float
         */
        private function calculateAllowedBaseTax(Creditmemo $creditMemo): float
        {
            $invoice = $creditMemo->getInvoice();
            $order = $creditMemo->getOrder();

            if ($invoice !== null)
            {
                $amount = $invoice->getBaseTaxAmount() - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::BASE_TAX_AMOUNT);
            }
            else
            {
                $amount = $order->getBaseTaxInvoiced() - $order->getBaseTaxRefunded();
            }

            return (float) $amount - $creditMemo->getBaseTaxAmount();
        }

        /**
         * Calculate allowed to Credit Memo discount tax compensation amount
         *
         * @access private
         *
         * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
         *
         * @return float
         */
        private function calculateAllowedDiscountTaxCompensation(Creditmemo $creditMemo): float
        {
            $invoice = $creditMemo->getInvoice();
            $order = $creditMemo->getOrder();

            if ($invoice)
            {
                $amount = $invoice->getDiscountTaxCompensationAmount()
                    + $invoice->getShippingDiscountTaxCompensationAmount()
                    - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::DISCOUNT_TAX_COMPENSATION_AMOUNT)
                    - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::SHIPPING_DISCOUNT_TAX_COMPENSATION_AMOUNT);
            }
            else
            {
                $amount = $order->getDiscountTaxCompensationInvoiced()
                    + $order->getShippingDiscountTaxCompensationAmount()
                    - $order->getDiscountTaxCompensationRefunded()
                    - $order->getShippingDiscountTaxCompensationRefunded();
            }

            return (float) $amount
                - $creditMemo->getDiscountTaxCompensationAmount()
                - $creditMemo->getShippingDiscountTaxCompensationAmount();
        }

        /**
         * Calculate allowed to Credit Memo discount tax compensation amount in the base currency
         *
         * @access private
         *
         * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
         *
         * @return float
         */
        private function calculateAllowedBaseDiscountTaxCompensation(Creditmemo $creditMemo): float
        {
            $invoice = $creditMemo->getInvoice();
            $order = $creditMemo->getOrder();

            if ($invoice)
            {
                $amount = $invoice->getBaseDiscountTaxCompensationAmount()
                    + $invoice->getBaseShippingDiscountTaxCompensationAmnt()
                    - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::BASE_DISCOUNT_TAX_COMPENSATION_AMOUNT)
                    - $this->calculateInvoiceRefundedAmount($invoice, CreditmemoInterface::BASE_SHIPPING_DISCOUNT_TAX_COMPENSATION_AMNT);
            }
            else
            {
                $amount = $order->getBaseDiscountTaxCompensationInvoiced()
                    + $order->getBaseShippingDiscountTaxCompensationAmnt()
                    - $order->getBaseDiscountTaxCompensationRefunded()
                    - $order->getBaseShippingDiscountTaxCompensationRefunded();
            }

            return (float) $amount
                - $creditMemo->getBaseShippingDiscountTaxCompensationAmnt()
                - $creditMemo->getBaseDiscountTaxCompensationAmount();
        }

        /**
         * Calculate refunded amount for invoice
         *
         * @access private
         *
         * @param \Magento\Sales\Model\Order\Invoice $invoice
         * @param string $field
         *
         * @return float
         */
        private function calculateInvoiceRefundedAmount(Invoice $invoice, string $field): float
        {
            return empty($invoice->getId())
                ? 0
                : $this->getResourceInvoice()->calculateRefundedAmount((int)$invoice->getId(), $field);
        }

        /**
         * Gets Is Active flag
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
         * Resource Invoice property
         *
         * @access private
         *
         * @var \Magento\Sales\Model\ResourceModel\Order\Invoice $_resourceInvoice
         */
        private $_resourceInvoice;

        /**
         * Gets Resource Invoice
         *
         * @access protected
         *
         * @return \Magento\Sales\Model\ResourceModel\Order\Invoice
         */
        protected function getResourceInvoice(): ResourceInvoice
        {
            return $this->_resourceInvoice;
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

        /**
         * Tax Config property
         *
         * @access private
         *
         * @var \Magento\Tax\Model\Config $_taxConfig
         */
        private $_taxConfig;

        /**
         * Gets Tax Config
         *
         * @access protected
         *
         * @return \Magento\Tax\Model\Config
         */
        protected function getTaxConfig(): TaxConfig
        {
            return $this->_taxConfig;
        }
    }
?>