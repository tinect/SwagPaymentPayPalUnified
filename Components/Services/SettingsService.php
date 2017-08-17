<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace SwagPaymentPayPalUnified\Components\Services;

use Doctrine\DBAL\Connection;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Shop\DetachedShop;
use SwagPaymentPayPalUnified\Components\DependencyProvider;
use SwagPaymentPayPalUnified\Models\Settings;
use SwagPaymentPayPalUnified\PayPalBundle\Components\SettingsServiceInterface;
use SwagPaymentPayPalUnified\PayPalBundle\Components\SettingsTable;

class SettingsService implements SettingsServiceInterface
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var Connection
     */
    private $dbalConnection;

    /**
     * @var DetachedShop
     */
    private $shop;

    /**
     * @var DependencyProvider
     */
    private $dependencyProvider;

    /**
     * @param ModelManager       $modelManager
     * @param DependencyProvider $dependencyProvider
     */
    public function __construct(
        ModelManager $modelManager,
        DependencyProvider $dependencyProvider
    ) {
        $this->dependencyProvider = $dependencyProvider;

        $this->modelManager = $modelManager;
        $this->dbalConnection = $modelManager->getConnection();

        $this->refreshDependencies();
    }

    /**
     * {@inheritdoc}
     */
    public function refreshDependencies()
    {
        $this->shop = $this->dependencyProvider->getShop();
    }

    /**
     * {@inheritdoc}
     */
    public function getSettings($shopId = null, $settingsType = SettingsTable::GENERAL)
    {
        //If this function is being called in the storefront, the shopId parameter is
        //not required, because it's being provided during the DI.
        $shopId = $shopId === null ? $this->shop->getId() : $shopId;

        switch ($settingsType) {
            case SettingsTable::GENERAL:
                return $this->modelManager->getRepository(Settings\General::class)->findOneBy(['shopId' => $shopId]);
            case SettingsTable::EXPRESS_CHECKOUT:
                return $this->modelManager->getRepository(Settings\ExpressCheckout::class)->findOneBy(['shopId' => $shopId]);
            case SettingsTable::INSTALLMENTS:
                return $this->modelManager->getRepository(Settings\Installments::class)->findOneBy(['shopId' => $shopId]);
            case SettingsTable::PLUS:
                return $this->modelManager->getRepository(Settings\Plus::class)->findOneBy(['shopId' => $shopId]);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function get($column, $settingsType = SettingsTable::GENERAL)
    {
        if ($this->shop === null) {
            throw new \RuntimeException('Could not retrieve a single setting without a shop instance.');
        }

        $table = $this->getTableByType($settingsType);

        return $this->dbalConnection->createQueryBuilder()
            ->select($column)
            ->from($table)
            ->where('shop_id = :shopId')
            ->setParameter('shopId', $this->shop->getId())
            ->execute()->fetchColumn();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function hasSettings($settingsType = SettingsTable::GENERAL)
    {
        if ($this->shop === null) {
            return false;
        }

        $table = $this->getTableByType($settingsType);

        return (bool) $this->dbalConnection->createQueryBuilder()
            ->select('id IS NOT NULL')
            ->from($table)
            ->where('shop_id = :shopId')
            ->setParameter('shopId', $this->shop->getId())
            ->execute()->fetchColumn();
    }

    /**
     * A helper function that returns the proper table name by the given settings type.
     *
     * @param string $settingsType
     *
     * @throws \RuntimeException
     *
     * @return string
     *
     * @see SettingsTable
     */
    private function getTableByType($settingsType)
    {
        switch ($settingsType) {
            case SettingsTable::GENERAL:
                return 'swag_payment_paypal_unified_settings_general';
            case SettingsTable::EXPRESS_CHECKOUT:
                return  'swag_payment_paypal_unified_settings_express';
            case SettingsTable::INSTALLMENTS:
                return 'swag_payment_paypal_unified_settings_installments';
            case SettingsTable::PLUS:
                return 'swag_payment_paypal_unified_settings_plus';
            default:
                throw new \RuntimeException('The provided table ' . $settingsType . ' is not supported');
                break;
        }
    }
}
