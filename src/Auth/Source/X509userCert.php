<?php
declare(strict_types=1);

namespace SimpleSAML\Module\authX509toSAML\Auth\Source;

use SimpleSAML\Auth\Source;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XHTML\Template;

/**
 * This class implements x509 certificate authentication in essence
 * translating the x509 certificate to a SAML Assertion
 *
 * @author Ioannis Kakavas <ikakavas@noc.grnet.gr>
 */
class X509userCert extends Source
{

    private array $config;

    /**
     * Constructor for this authentication source.
     *
     * All subclasses who implement their own constructor must call this
     * constructor before using $config for anything.
     *
     * @param array $info  Information about this authentication source.
     * @param array &$config  Configuration for this authentication source.
     */
    public function __construct(array $info, array &$config)
    {
        assert('is_array($info)');
        assert('is_array($config)');
        parent::__construct($info, $config);
        $this->config = $config;
        return;
    }
    /**
     * Finish a failed authentication.
     *
     * This function can be overloaded by a child authentication
     * class that wish to perform some operations on failure
     *
     * @param array &$state  Information about the current authentication.
     */
    public function authFailed(array &$state): void
    {
          $this->showError(
              $state['authX509toSAML.error'],
              []
          );
    }


    /**
     *
     * The client ssl authentication is already performed in Apache. This method
     * maps the necessary attributes from the certificate to SAML attributes for
     * the Attribute Statement of the SAML Assertion.
     * On success, the user is logged in without going through the login page.
     * On failure, The authX509toSAML:X509error.php template is
     * loaded.
     *
     * @param array &$state  Information about the current authentication.
     *
     * @return void
     */
    public function authenticate(array &$state): void
    {
        assert('is_array($state)');

        if (
            !isset($_SERVER['SSL_CLIENT_CERT']) ||
            ($_SERVER['SSL_CLIENT_CERT'] == '')
        ) {
            $state['authX509toSAML.error'] = "NOCERT";
            $this->authFailed($state);
            assert('FALSE'); // NOTREACHED
            return;
        }

        $client_cert = $_SERVER['SSL_CLIENT_CERT'];
        $client_cert_data = openssl_x509_parse($client_cert);
        if ($client_cert_data == false) {
            Logger::error('authX509toSAML: invalid cert');
            $state['authX509toSAML.error'] = "INVALIDCERT";
            $this->authFailed($state);

            assert('FALSE'); // NOTREACHED
            return;
        }

        $attributes = [];
        /**
         * Load values from configuration or fallback to defaults
         *
         */
        if (!array_key_exists('authX509toSAML:cert_name_attribute', $this->config)) {
            $cert_name_attribute = 'CN';
        } else {
            $cert_name_attribute = $this->config['authX509toSAML:cert_name_attribute'];
        }
        if (!array_key_exists('authX509toSAML:assertion_name_attribute', $this->config)) {
            $assertion_name_attribute = 'displayName';
        } else {
            $assertion_name_attribute = $this->config['authX509toSAML:assertion_name_attribute'];
        }
        if (!array_key_exists('authX509toSAML:assertion_dn_attribute', $this->config)) {
            $assertion_dn_attribute = 'distinguishedName';
        } else {
            $assertion_dn_attribute = $this->config['authX509toSAML:assertion_dn_attribute'];
        }
        if (!array_key_exists('authX509toSAML:assertion_issuer_dn_attribute', $this->config)) {
            $assertion_issuer_dn_attribute = 'voPersonCertificateIssuerDN';
        } else {
            $assertion_issuer_dn_attribute = $this->config['authX509toSAML:assertion_issuer_dn_attribute'];
        }
        if (!array_key_exists('authX509toSAML:assertion_o_attribute', $this->config)) {
            $assertion_o_attribute = 'o';
        } elseif (
            !empty($this->config['authX509toSAML:assertion_o_attribute'])
            && is_string($this->config['authX509toSAML:assertion_o_attribute'])
        ) {
            $assertion_o_attribute = $this->config['authX509toSAML:assertion_o_attribute'];
        }
        if (!array_key_exists('authX509toSAML:assertion_assurance_attribute', $this->config)) {
            $assertion_assurance_attribute = 'eduPersonAssurance';
        } else {
            $assertion_assurance_attribute = $this->config['authX509toSAML:assertion_assurance_attribute'];
        }
        if (!array_key_exists('authX509toSAML:parse_san_emails', $this->config)) {
            $parse_san_emails = true;
        } else {
            $parse_san_emails = $this->config['authX509toSAML:parse_san_emails'];
        }
        if (!array_key_exists('authX509toSAML:parse_policy', $this->config)) {
            $parse_policy = true;
        } else {
            $parse_policy = $this->config['authX509toSAML:parse_policy'];
        }
        if (!array_key_exists('authX509toSAML:export_eppn', $this->config)) {
            $export_eppn = false;
        } else {
            $export_eppn = $this->config['authX509toSAML:export_eppn'];
        }

        // Get the subject of the certificate
        if (array_key_exists('name', $client_cert_data)) {
            $attributes[$assertion_dn_attribute] = [$client_cert_data['name']];
            $state['UserID'] = $client_cert_data['name'];
        }

        Logger::debug('X509userCert subject: ' . var_export($client_cert_data['subject'], true));
        if (array_key_exists($cert_name_attribute, $client_cert_data['subject'])) {
            if (is_array($client_cert_data['subject'][$cert_name_attribute])) {
                $client_cert_data['subject'][$cert_name_attribute] = end($client_cert_data['subject'][$cert_name_attribute]);
            }
            if ($export_eppn) {
                $name_tokens = explode(" ", $client_cert_data['subject'][$cert_name_attribute]);
                $eppn = '';
                foreach ($name_tokens as $token) {
                    if (strpos($token, '@') !== false) {
                        $attributes['eduPersonPrincipalName'] = [$token];
                        $eppn = $token;
                        break;
                    }
                }
                // Now remove the eppn from the $assertion_name_attribute
                $attributes[$assertion_name_attribute] = [
                    str_replace($eppn, '', $client_cert_data['subject'][$cert_name_attribute])
                ];
            } else {
                $attributes[$assertion_name_attribute] = [$client_cert_data['subject'][$cert_name_attribute]];
            }
        }
        // Attempt to extract issuer DN information
        if (!empty($client_cert_data['issuer'])) {
            if (is_array($client_cert_data['issuer'])) {
                $issuer_dn = '';
                foreach ($client_cert_data['issuer'] as $key => $value) {
                    if (is_array($value)) {
                        $issuer_dn .= '/' . $key . '=' . implode("/$key=", $value);
                    } else {
                        $issuer_dn .= '/' . $key . '=' . $value;
                    }
                }
                // $flattened = $issuer;
                // array_walk($flattened, function(&$value, $key) {
                //     $value = "/$key=$value";
                // });
                $attributes[$assertion_issuer_dn_attribute] = [$issuer_dn];
            } elseif (is_string($client_cert_data['issuer'])) {
                $attributes[$assertion_issuer_dn_attribute] = [$client_cert_data['issuer']];
            }
        }
        // Attempt to parse Subject Alternate Names for email addresses
        if ($parse_san_emails) {
            $attributes['mail'] = [];
            if (array_key_exists('subjectAltName', $client_cert_data['extensions'])) {
                if (
                    is_string($client_cert_data['extensions']['subjectAltName'])
                    && substr($client_cert_data['extensions']['subjectAltName'], 0, 6) === "email:"
                ) {
                    $attributes['mail'][] = str_replace('email:', '', $client_cert_data['extensions']['subjectAltName']);
                } elseif (is_array($client_cert_data['extensions']['subjectAltName'])) {
                    foreach ($client_cert_data['extensions']['subjectAltName'] as $subjectAltName) {
                        if (substr($subjectAltName, 0, 6) === "email:") {
                            $attributes['mail'][] = str_replace('email:', '', $subjectAltName);
                        }
                    }
                }
            }
        }
        // Attempt to parse organisation name from certificate subject
        if (
            isset($assertion_o_attribute)
            && !empty($client_cert_data['subject']['O'])
            && is_string($client_cert_data['subject']['O'])
        ) {
            $attributes[$assertion_o_attribute] = [$client_cert_data['subject']['O']];
        }
        // Attempt to parse certificatePolicies extensions
        if ($parse_policy) {
            if (
                !empty($client_cert_data['extensions']['certificatePolicies'])
                && is_string($client_cert_data['extensions']['certificatePolicies'])
            ) {
                $attributes[$assertion_assurance_attribute] = [];
                if (
                    preg_match_all(
                        '/Policy: ([\d\.\d]+)/',
                        $client_cert_data['extensions']['certificatePolicies'],
                        $matches
                    )
                ) {
                    if (count($matches) > 1) {
                        foreach ($matches[1] as $policy) {
                            $attributes[$assertion_assurance_attribute][] = $policy;
                        }
                    }
                }
            }
        }

        assert('is_array($attributes)');
        $state['Attributes'] = $attributes;
        $this->authSuccesful($state);
        assert('FALSE'); /* NOTEREACHED */
        return;
    }


    /**
     * Finish a succesfull authentication.
     *
     * This function can be overloaded by a child authentication
     * class that wish to perform some operations after login.
     *
     * @param array &$state  Information about the current authentication.
     */
    public function authSuccesful(array &$state): void
    {
        Source::completeAuth($state);

        assert('FALSE'); /* NOTREACHED */
        return;
    }

    /**
     * @param   string  $errorCode
     * @param   array   $parameters
     *
     * @return void
     * @throws \SimpleSAML\Error\ConfigurationError
     */
    private function showError(string $errorCode, array $parameters): void
    {
        // Save state and redirect
        $url = Module::getModuleURL('/userid/errorReport');
        $params = [
            'errorcode' => $errorCode,
            'parameters' => $parameters
        ];

        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, $params);
    }
}
