<?php

namespace SimpleSAML\Module\authWebauthn\Auth\Process;

/**
 * Filter that requires valid Webauthn
 *
 * @author Martin van Es, SURFnet.
 * @package SimpleSAMLphp
 */

class WebAuthn extends \SimpleSAML\Auth\ProcessingFilter
{
    /**
     * The attribute we should generate the targeted id from, or NULL if we should use the
     * UserID.
     */
    private $attribute = null;

    /**
     * The attribute we should generate the targeted id from, or NULL if we should use the
     * UserID.
     */
    private $purpose = null;

    /**
     * Initialize this filter.
     *
     * @param array $config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        assert(is_array($config));

        if (array_key_exists('attributename', $config)) {
            if (!is_string($config['attributename'])) {
                throw new \Exception('Invalid attribute name given to authWebauthn:WebAuthn filter.');
            }
            $this->attribute = $config['attributename'];
        }
        if (array_key_exists('purpose', $config)) {
            if (!in_array($config['purpose'], ['register', 'validate'])) {
                throw new \Exception('Invalid purpose given to authWebauthn:WebAuthn filter.');
            }
            $this->purpose = $config['purpose'];
        }

    }

    /**
     * Apply filter to add the targeted ID.
     *
     * @param array &$state  The current state.
     */
    public function process(&$state)
    {
        assert(is_array($state));
        assert(array_key_exists('Attributes', $state));

        if (!array_key_exists($this->attribute, $state['Attributes'])) {
                throw new \Exception('authWebauthn:WebAuthn: Missing attribute \''.$this->attribute.
                    '\', which is needed to proceed.');
            }

        $purpose = $this->purpose;
        $userID = $state['Attributes'][$this->attribute][0];
        \SimpleSAML\Logger::info("WebAuthn ProcFilter $purpose for $userID");
//         \SimpleSAML\Logger::info("WebAuthn ProcFilter state:" . print_r($state, true));

        $state['Attributes']['webauthn'] = ["$purpose $userID"];
        $state['userID'] = $userID;
        $state['Purpose'] = $purpose;

        // Save state and redirect
        $id = \SimpleSAML\Auth\State::saveState($state, 'authWebauthn:webauthn');
        $url = \SimpleSAML\Module::getModuleURL('authWebauthn/webauthn.php');
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['StateId' => $id]);
//         \SimpleSAML\Utils\HTTP::submitPOSTData($url, ['StateId' => $id]);

    }

    public function success(&$state)
    {
        $purpose = $state['Purpose'];
        $userID = $state['userID'];
        \SimpleSAML\Logger::info("WebAuthn ProcFilter $purpose for $userID success");
        \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
    }

    public function fail(&$state)
    {
        $purpose = $state['Purpose'];
        $userID = $state['userID'];
//         \SimpleSAML\Logger::info("WebAuthn ProcFilter $purpose for $userID fail");
        \SimpleSAML\Logger::info("WebAuthn ProcFilter state:" . print_r($state, true));

        \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
    }

}
