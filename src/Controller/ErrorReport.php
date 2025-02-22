<?php

declare(strict_types=1);


namespace SimpleSAML\Module\authX509toSAML\Controller;

use SAML2\Constants as C;
use SimpleSAML\Assert\Assert;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Locale\Translate;
use SimpleSAML\Module\adfs\IdP\ADFS as ADFS_IdP;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\VarExporter\VarExporter;
use SimpleSAML\{Logger, Configuration, Error, Session, Utils};
use Twig\TwigFunction;


/**
 * Controller class for the admin module.
 *
 * This class serves the federation views available in the module.
 *
 * @package SimpleSAML\Module\admin
 */
class ErrorReport
{
  /** @var \SimpleSAML\Utils\HTTP */
  protected Utils\HTTP $httpUtils;

  /**
   * Sandbox constructor.
   *
   * @param   \SimpleSAML\Configuration  $config   The configuration to use.
   * @param   \SimpleSAML\Session        $session  The current user session.
   */
  public function __construct(
    protected Configuration $config,
    protected Session $session
  ) {
    $this->httpUtils = new Utils\HTTP();
  }

  /**
   * Display the sandbox page
   *
   * @return \SimpleSAML\XHTML\Template
   */
  public function main(Request $request, string $as = null): Template
  {
    $errorCode = $request->query->get('errorCode');
    $parameters = $request->query->get('parameters');

    $parameters = json_decode(base64_decode(urldecode($parameters)));

    // redirect the user back to this page to clear the POST request
    $t = new Template($this->config, 'authX509toSAML:errorreport.twig');
    $t->data['errorCode'] = $errorCode;
    $t->data['errorCodes'] = Error\ErrorCodes::getAllErrorCodeMessages();
    foreach ($parameters as $key => $val) {
        $t->data[$key] = $val;
    }
    $t->data['items'] = [
        'HTTP_HOST' => $request->getHost(),
        'HTTPS' => $request->isSecure() ? ['on'] : [],
        'SERVER_PROTOCOL' => $request->getProtocolVersion(),
        'getBaseURL' => $this->httpUtils->getBaseURL(),
        'getSelfHost' => $this->httpUtils->getSelfHost(),
        'getSelfHostWithNonStandardPort' => $this->httpUtils->getSelfHostWithNonStandardPort(),
        'getSelfURLHost' => $this->httpUtils->getSelfURLHost(),
        'getSelfURLNoQuery' => $this->httpUtils->getSelfURLNoQuery(),
        'getSelfHostWithPath' => $this->httpUtils->getSelfHostWithPath(),
        'getSelfURL' => $this->httpUtils->getSelfURL(),
    ];

    Logger::debug('[ErrorReport]::main::data' . var_export($t->data, true));

    $twig = $t->getTwig();
    // TWIG does not have an htmlspecialchars function. We will pass in the one from php
    $twig->addFunction(new TwigFunction('htmlspecialchars', 'htmlspecialchars'));

    return $t;
  }

}
