<?php

namespace SimpleSAML\Module\authwebauthn\Auth\Process;

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
     * The attribute we should generate the targeted id from, or NULL if we should use the
     * UserID.
     */
    private $database = null;

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

        if (array_key_exists('id', $config)) {
            if (!is_string($config['id'])) {
                throw new \Exception('Invalid id name given to authwebauthn:WebAuthn filter.');
            }
            $this->id = $config['id'];
        }
        if (array_key_exists('purpose', $config)) {
            if (!in_array($config['purpose'], ['register', 'validate'])) {
                throw new \Exception('Invalid purpose given to authwebauthn:WebAuthn filter.');
            }
            $this->purpose = $config['purpose'];
        }
        if (array_key_exists('database', $config)) {
            if (!is_string($config['database'])) {
                throw new \Exception('Invalid database name given to authwebauthn:WebAuthn filter.');
            }
            $this->database = $config['database'];
            if (!file_exists($this->database)) {
                $db = new \SQLite3($this->database);
                if (!$db) {
                    \SimpleSAML\Logger::info(sprintf('Cannot create user database - is the html directory writable by the web server? If not: "mkdir %s; chmod 777 %s"', $config['database'], $config['database']));
                    throw new \Exception(sprintf("cannot create %s - see error log", $config['database']));
                }
                $query = 'CREATE TABLE "keys" ("user" TEXT NOT NULL UNIQUE, "webauthnkeys" TEXT NOT NULL)';
                $result = $db->query($query);
            }
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

        if (!array_key_exists($this->id, $state['Attributes'])) {
                throw new \Exception('authwebauthn:WebAuthn: Missing attribute \''.$this->id.
                    '\', which is needed to proceed.');
            }

        $state['userID'] = $state['Attributes'][$this->id][0];
        $state['Purpose'] = $this->purpose;
        $state['db'] = $this->database;

        // Save state and redirect
        $id = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');
        $url = \SimpleSAML\Module::getModuleURL('authwebauthn/webauthn.php');
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['StateId' => $id]);
//         \SimpleSAML\Utils\HTTP::submitPOSTData($url, ['StateId' => $id]);

    }

    public function success(&$state)
    {
        \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
    }

    public function fail(&$state)
    {
        $state['Attributes'] = [];
        \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
    }

}
