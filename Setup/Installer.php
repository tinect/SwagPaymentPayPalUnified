<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPaymentPayPalUnified\Setup;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Plugin\Plugin;
use SwagPaymentPayPalUnified\Components\PaymentMethodProvider;

class Installer
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CrudService
     */
    private $attributeCrudService;

    /**
     * @var string
     */
    private $bootstrapPath;

    /**
     * Installer constructor.
     *
     * @param ModelManager $modelManager
     * @param Connection   $connection
     * @param CrudService  $attributeCrudService
     * @param string       $bootstrapPath
     */
    public function __construct(
        ModelManager $modelManager,
        Connection $connection,
        CrudService $attributeCrudService,
        $bootstrapPath
    ) {
        $this->modelManager = $modelManager;
        $this->connection = $connection;
        $this->attributeCrudService = $attributeCrudService;
        $this->bootstrapPath = $bootstrapPath;
    }

    /**
     * @throws InstallationException
     *
     * @return bool
     */
    public function install()
    {
        if ($this->hasPayPalClassicInstalled()) {
            throw new InstallationException('This plugin can not be used while PayPal Classic, PayPal Plus or PayPal Installments are installed and active.');
        }

        $this->createDatabaseTables();
        $this->createUnifiedPaymentMethod();
        $this->createInstallmentsPaymentMethod();
        $this->createAttributes();
        $this->createDocumentTemplates();
        $this->migrate();

        return true;
    }

    /**
     * @return bool
     */
    private function hasPayPalClassicInstalled()
    {
        $classicPlugin = $this->modelManager->getRepository(Plugin::class)->findOneBy([
            'name' => 'SwagPaymentPaypal',
            'active' => 1,
        ]);
        $classicPlusPlugin = $this->modelManager->getRepository(Plugin::class)->findOneBy([
            'name' => 'SwagPaymentPaypalPlus',
            'active' => 1,
        ]);
        $classicInstallmentsPlugin = $this->modelManager->getRepository(Plugin::class)->findOneBy([
            'name' => 'SwagPaymentPayPalInstallments',
            'active' => 1,
        ]);

        return $classicPlugin !== null || $classicPlusPlugin !== null || $classicInstallmentsPlugin !== null;
    }

    private function createDatabaseTables()
    {
        $sql = file_get_contents($this->bootstrapPath . '/Setup/Assets/tables.sql');

        $this->connection->query($sql);
    }

    private function createAttributes()
    {
        $this->attributeCrudService->update('s_order_attributes', 'swag_paypal_unified_payment_type', 'string');
        $this->attributeCrudService->update(
            's_core_paymentmeans_attributes',
            'swag_paypal_unified_display_in_plus_iframe',
            'boolean',
            [
                'position' => -100,
                'displayInBackend' => true,
                'label' => 'Display in PayPal Plus iFrame',
                'helpText' => 'Activate this option, to display this payment method in the PayPal Plus iFrame',
            ]
        );

        $this->modelManager->generateAttributeModels(['s_order_attributes', 's_core_paymentmeans_attributes']);
    }

    private function createDocumentTemplates()
    {
        $this->removeDocumentTemplates();

        $sql = "
			INSERT INTO `s_core_documents_box` (`documentID`, `name`, `style`, `value`) VALUES
			(1, 'PayPal_Unified_Instructions_Footer', 'width: 170mm;\r\nposition:fixed;\r\nbottom:-20mm;\r\nheight: 15mm;', :footerValue),
			(1, 'PayPal_Unified_Instructions_Content', :contentStyle, :contentValue);
		";

        //Load the assets
        $instructionsContent = file_get_contents($this->bootstrapPath . '/Setup/Assets/Document/PayPal_Unified_Instructions_Content.html');
        $instructionsContentStyle = file_get_contents($this->bootstrapPath . '/Setup/Assets/Document/PayPal_Unified_Instructions_Content_Style.css');
        $instructionsFooter = file_get_contents($this->bootstrapPath . '/Setup/Assets/Document/PayPal_Unified_Instructions_Footer.html');

        $this->connection->executeQuery($sql, [
            'footerValue' => $instructionsFooter,
            'contentStyle' => $instructionsContentStyle,
            'contentValue' => $instructionsContent,
        ]);
    }

    private function createUnifiedPaymentMethod()
    {
        $existingPayment = $this->modelManager->getRepository(Payment::class)->findOneBy([
            'name' => PaymentMethodProvider::PAYPAL_UNIFIED_PAYMENT_METHOD_NAME,
        ]);

        if ($existingPayment !== null) {
            //If the payment does already exist, we don't need to add it again.
            return;
        }

        $entity = new Payment();
        $entity->setActive(false);
        $entity->setName(PaymentMethodProvider::PAYPAL_UNIFIED_PAYMENT_METHOD_NAME);
        $entity->setDescription('PayPal');
        $entity->setAdditionalDescription($this->getUnifiedPaymentLogo() . 'Bezahlung per PayPal - einfach, schnell und sicher.');
        $entity->setAction('PaypalUnified');

        $this->modelManager->persist($entity);
        $this->modelManager->flush($entity);
    }

    private function createInstallmentsPaymentMethod()
    {
        $existingPayment = $this->modelManager->getRepository(Payment::class)->findOneBy([
            'name' => PaymentMethodProvider::PAYPAL_INSTALLMENTS_PAYMENT_METHOD_NAME,
        ]);

        if ($existingPayment !== null) {
            //If the payment does already exist, we don't need to add it again.
            return;
        }

        $entity = new Payment();
        $entity->setActive(false);
        $entity->setName(PaymentMethodProvider::PAYPAL_INSTALLMENTS_PAYMENT_METHOD_NAME);
        $entity->setDescription('Ratenzahlung Powered by PayPal');
        $entity->setAdditionalDescription('Wir ermöglichen Ihnen die Finanzierung Ihres Einkaufs mithilfe der Ratenzahlung Powered by PayPal. In Sekundenschnelle, vollständig online, vorbehaltlich Bonitätsprüfung.');
        $entity->setAction('PaypalUnifiedInstallments');

        $this->modelManager->persist($entity);
        $this->modelManager->flush($entity);
    }

    private function removeDocumentTemplates()
    {
        $sql = "DELETE FROM s_core_documents_box WHERE `name` LIKE 'PayPal_Unified%'";
        $this->connection->exec($sql);
    }

    private function migrate()
    {
        $sql = file_get_contents($this->bootstrapPath . '/Setup/Assets/migration.sql');

        $this->connection->query($sql);
    }

    /**
     * @return string
     */
    private function getUnifiedPaymentLogo()
    {
        return '<!-- PayPal Logo -->'
        . '<a onclick="window.open(this.href, \'olcwhatispaypal\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=400, height=500\'); return false;"'
        . ' href="https://www.paypal.com/de/cgi-bin/webscr?cmd=xpt/cps/popup/OLCWhatIsPayPal-outside" target="_blank">'
        . '<img src="{link file=\'frontend/_public/src/img/sidebar-paypal-generic.png\' fullPath}" alt="Logo \'PayPal empfohlen\'">'
        . '</a><br>' . '<!-- PayPal Logo -->';
    }
}
