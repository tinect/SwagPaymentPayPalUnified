<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use SwagPaymentPayPalUnified\Components\Installments\FinancingOptionsHandler;
use SwagPaymentPayPalUnified\Components\Services\Installments\CompanyInfoService;
use SwagPaymentPayPalUnified\Components\Services\Installments\InstallmentsRequestService;
use SwagPaymentPayPalUnified\PayPalBundle\Components\LoggerServiceInterface;
use SwagPaymentPayPalUnified\PayPalBundle\Resources\InstallmentsResource;
use SwagPaymentPayPalUnified\PayPalBundle\Structs\Installments\FinancingResponse;

class Shopware_Controllers_Widgets_PaypalUnifiedInstallments extends Enlight_Controller_Action
{
    /**
     * @var LoggerServiceInterface
     */
    private $logger;

    /**
     * @var InstallmentsRequestService
     */
    private $installmentsRequestService;

    /**
     * @var InstallmentsResource
     */
    private $installmentsResource;

    /**
     * @var CompanyInfoService
     */
    private $companyInfoService;

    public function preDispatch()
    {
        $this->logger = $this->get('paypal_unified.logger_service');
        $this->installmentsResource = $this->get('paypal_unified.installments_resource');
        $this->installmentsRequestService = $this->get('paypal_unified.installments.installments_request_service');
        $this->companyInfoService = $this->get('paypal_unified.installments.company_info_service');
    }

    /**
     * Requests a list of all financing entries and prepares the template to display
     * the cheapest one.
     *
     * @see Shopware_Controllers_Widgets_PaypalUnifiedInstallments::applyCheapestRateTemplate()
     */
    public function cheapestRateAction()
    {
        $this->applyCheapestRateTemplate();
    }

    /**
     * Requests a list of all financing entries and prepares the template to display
     * all of them.
     *
     * @see Shopware_Controllers_Widgets_PaypalUnifiedInstallments::applyCompleteListTemplate()
     */
    public function listAction()
    {
        $this->applyCompleteListTemplate();
    }

    /**
     * Requests a list of all financing entries and prepares the template to display
     * all of them.
     *
     * It's exactly the same procedure as the listAction, but in this case, another template will be loaded by shopware.
     *
     * @see Shopware_Controllers_Widgets_PaypalUnifiedInstallments::applyCompleteListTemplate()
     */
    public function modalContentAction()
    {
        $this->applyCompleteListTemplate();
    }

    /**
     * Interprets the request for a cheapest rate.
     * It collects required data and assigns it to the template.
     */
    private function applyCheapestRateTemplate()
    {
        $productPrice = $this->Request()->getParam('productPrice');

        //The page type is only required in the templates. Its a value indicating if
        //the user is on a detail or on the cart page.
        $pageType = $this->Request()->getParam('pageType');

        $response = $this->installmentsRequestService->getList($productPrice);

        if (!isset($response['financing_options'][0])) {
            $this->logger->warning('Could not find financing options in response', ['payload' => $response, 'product-price' => $productPrice]);

            return;
        }

        // The result must be sorted to get the cheapest rate, since it's being delivered unsorted from PayPal
        $financingResponseStruct = FinancingResponse::fromArray($response['financing_options'][0]);
        $optionsHandler = new FinancingOptionsHandler($financingResponseStruct);
        $financingResponseStruct = $optionsHandler->sortOptionsBy(FinancingOptionsHandler::SORT_BY_MONTHLY_PAYMENT);
        $qualifyingFinancingOptions = $financingResponseStruct->toArray()['qualifyingFinancingOptions'];

        //The cheapest rate is now the first entry in the struct.
        $this->View()->assign('paypalInstallmentsOption', $qualifyingFinancingOptions[0]); //index 0 because it was sorted above.
        $this->View()->assign('paypalInstallmentsProductPrice', $productPrice);
        $this->View()->assign('paypalInstallmentsCompanyInfo', $this->companyInfoService->getCompanyInfo());

        //Depending on this value either the detail or the cart upstream presentment will be loaded
        $this->View()->assign('paypalInstallmentsPageType', $pageType);
    }

    /**
     * Interprets the request for all rates.
     * It collects required data and assigns it to the template.
     *
     * Not only the rates will be assigned but also the hasStar property of each entry will
     * be set in here.
     */
    private function applyCompleteListTemplate()
    {
        $productPrice = $this->Request()->getParam('productPrice');

        $response = $this->installmentsRequestService->getList($productPrice);

        if (!isset($response['financing_options'][0])) {
            $this->logger->error('Could not find financing options in response', ['payload' => $response, 'product-price' => $productPrice]);

            return;
        }

        $financingResponseStruct = FinancingResponse::fromArray($response['financing_options'][0]);
        $optionsHandler = new FinancingOptionsHandler($financingResponseStruct);
        $qualifyingFinancingOptions = $optionsHandler->finalizeList();

        $this->View()->assign('paypalInstallmentsOptions', $qualifyingFinancingOptions);
        $this->View()->assign('paypalInstallmentsProductPrice', $productPrice);
        $this->View()->assign('paypalInstallmentsCompanyInfo', $this->companyInfoService->getCompanyInfo());
    }
}
