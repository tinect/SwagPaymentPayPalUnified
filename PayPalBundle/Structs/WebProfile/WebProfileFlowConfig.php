<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPaymentPayPalUnified\PayPalBundle\Structs\WebProfile;

class WebProfileFlowConfig
{
    /**
     * The merchant site URL to display after a bank transfer payment.
     * Valid for only the Giropay or bank transfer payment method in Germany.
     *
     * @var string
     */
    private $bankTxnPendingUrl;

    /**
     * Defines whether the buyer is presented with a Continue or Pay Now checkout flow.
     * Set useraction=commit in the request URI to present buyers with the Pay Now checkout flow.
     * Default is the Continue checkout flow.
     *
     * @var string
     */
    private $userAction;

    /**
     * The HTTP method to use to redirect the user to a return URL. Valid value is GET or POST.
     *
     * @var string
     */
    private $returnUriHttpMethod = 'POST';

    /**
     * @return string
     */
    public function getBankTxnPendingUrl()
    {
        return $this->bankTxnPendingUrl;
    }

    /**
     * @param string $bankTxnPendingUrl
     */
    public function setBankTxnPendingUrl($bankTxnPendingUrl)
    {
        $this->bankTxnPendingUrl = $bankTxnPendingUrl;
    }

    /**
     * @return string
     */
    public function getUserAction()
    {
        return $this->userAction;
    }

    /**
     * @param string $userAction
     */
    public function setUserAction($userAction)
    {
        $this->userAction = $userAction;
    }

    /**
     * @return string
     */
    public function getReturnUriHttpMethod()
    {
        return $this->returnUriHttpMethod;
    }

    /**
     * @param string $returnUriHttpMethod
     */
    public function setReturnUriHttpMethod($returnUriHttpMethod)
    {
        $this->returnUriHttpMethod = $returnUriHttpMethod;
    }

    /**
     * @param array $data
     *
     * @return WebProfileFlowConfig
     */
    public static function fromArray(array $data = [])
    {
        $flowConfig = new self();
        $flowConfig->setBankTxnPendingUrl($data['bank_txn_pending_url']);
        $flowConfig->setUserAction($data['user_action']);
        $flowConfig->setReturnUriHttpMethod($data['return_uri_http_method']);

        return $flowConfig;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'bank_txn_pending_url' => $this->getBankTxnPendingUrl(),
            'user_action' => $this->getUserAction(),
            'return_uri_http_method' => $this->getReturnUriHttpMethod(),
        ];
    }
}
