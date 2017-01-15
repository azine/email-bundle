<?php

namespace Azine\EmailBundle\Controller;

use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Azine\EmailBundle\Entity\SentEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Azine\EmailBundle\Services\TemplateProviderInterface;

/**
 * This controller provides the following actions:
 *
 * index: view a list of all your templates with the option to send a test mail with "dummy"-data to an email-address of your choice (see WebViewServiceInterface::getTemplatesForWebPreView() & WebViewServiceInterface::getTestMailAccounts) .
 * webPreView: shows the selected html- or txt-email-template filled with the dummy-data you defined (in the WebViewServiceInterface::getDummyVarsFor() function).
 * webView: shows an email that has been sent (and stored as SentEmail-entity in the database)
 * sendTestMail: sends an email filled with the dummy-data you defined to the selected email-address.
 * serveImage: serve an image from the template-image-folder
 *
 * @author dominik
 */

class AzineEmailTemplateController extends Controller
{

    /**
     * Show a set of options to view html- and text-versions of email in the browser and send them as emails to test-accounts
     */
    public function indexAction(Request $request)
    {
        $customEmail = $request->get('customEmail', 'custom@email.com');
        $templates = $this->get('azine_email_web_view_service')->getTemplatesForWebPreView();
        $emails = $this->get('azine_email_web_view_service')->getTestMailAccounts();

        return $this->get('templating')
                    ->renderResponse("AzineEmailBundle:Webview:index.html.twig",
                    array(	
                        'customEmail' => $customEmail,
                        'templates'   => $templates,
                        'emails'      => $emails,
                    ));
    }

    /**
     * Show a web-preview-version of an email-template, filled with dummy-content
     * @param string $format
     * @return Response
     */
    public function webPreViewAction(Request $request, $template, $format = null)
    {
        
        if ($format !== "txt") {
            $format = "html";
        }

        $locale = $request->getLocale();

        // merge request vars with dummyVars, but make sure request vars remain as they are.
        $emailVars = array_merge(array(), $request->query->all());
        $emailVars = $this->get('azine_email_web_view_service')->getDummyVarsFor($template, $locale, $emailVars);
        $emailVars = array_merge($emailVars, $request->query->all());

        // add the styles
        $emailVars = $this->getTemplateProviderService()->addTemplateVariablesFor($template, $emailVars);

        // add the from-email for the footer-text
        if (!array_key_exists('fromEmail', $emailVars)) {
            $noReply = $this->getParameter('azine_email_no_reply');
            $emailVars['fromEmail'] = $noReply['email'];
            $emailVars['fromName'] = $noReply['name'];
        }

        // set the emailLocale for the templates
        $emailVars['emailLocale'] = $locale;

        // replace absolute image-paths with relative ones.
        $emailVars = $this->getTemplateProviderService()->makeImagePathsWebRelative($emailVars, $locale);

        // add code-snippets
        $emailVars = $this->getTemplateProviderService()->addTemplateSnippetsWithImagesFor($template, $emailVars, $locale);

        // render & return email
        $response = $this->renderResponse("$template.$format.twig", $emailVars);

        // add campaign tracking params
        $campaignParams = $this->getTemplateProviderService()->getCampaignParamsFor($template, $emailVars);
        $campaignParams['utm_medium'] = 'webPreview';
        if(sizeof($campaignParams) > 0) {
            $htmlBody = $response->getContent();
            $htmlBody = $this->get("azine.email.bundle.twig.filters")->addCampaignParamsToAllUrls($htmlBody, $campaignParams);

            $emailOpenTrackingCodeBuilder = $this->get('azine_email_email_open_tracking_code_builder');
            if ($emailOpenTrackingCodeBuilder) {
                // add an image at the end of the html tag with the tracking-params to track email-opens
                $imgTrackingCode = $emailOpenTrackingCodeBuilder->getTrackingImgCode($template, $campaignParams, $emailVars, "dummy", "dummy@from.email.com", null, null);
                if ($imgTrackingCode && strlen($imgTrackingCode) > 0) {
                    // replace the tracking url, so no request is made to the real tracking system.
                    $imgTrackingCode = str_replace("://", "://webview-dummy-domain.", $imgTrackingCode);
                    $htmlCloseTagPosition = strpos($htmlBody, "</html>");
                    $htmlBody = substr_replace($htmlBody, $imgTrackingCode, $htmlCloseTagPosition, 0);
                }
            }
            $response->setContent($htmlBody);
        }

        // if the requested format is txt, remove the html-part
        if ($format == "txt") {
            // set the correct content-type
            $response->headers->set("Content-Type","text/plain");

            // cut away the html-part
            $content = $response->getContent();
            $textEnd = stripos($content, "<!doctype");
            $response->setContent(substr($content, 0, $textEnd));
        }

        return $response;
    }

    /**
     * Show a web-version of an email that has been sent to recipients and has been stored in the database.
     * @param Request $request
     * @param string $token
     * @return Response
     */
    public function webViewAction (Request $request, $token)
    {
        // find email recipients, template & params
        $sentEmail = $this->getSentEmailForToken($token);

        // check if the sent email is available
        if ($sentEmail !== null) {

            // check if the current user is allowed to see the email
            if ($this->userIsAllowedToSeeThisMail($sentEmail)) {

                $template = $sentEmail->getTemplate();
                $emailVars = $sentEmail->getVariables();

                // re-attach all entities to the EntityManager.
                $this->reAttachAllEntities($emailVars);

                // remove the web-view-token from the param-array
                $templateProvider = $this->getTemplateProviderService();
                unset($emailVars[$templateProvider->getWebViewTokenId()]);

                // render & return email
                $response = $this->renderResponse("$template.html.twig", $emailVars);

                $campaignParams = $templateProvider->getCampaignParamsFor($template, $emailVars);

                if (sizeof($campaignParams) > 0) {
                    $response->setContent($this->get("azine.email.bundle.twig.filters")->addCampaignParamsToAllUrls($response->getContent(), $campaignParams));
                }

                return $response;

            // if the user is not allowed to see this mail
            } else {
                $msg = $this->get('translator')->trans('web.pre.view.test.mail.access.denied');
                throw new AccessDeniedException($msg);
            }
        }

        // the parameters-array is null => the email is not available in webView
        $days = $this->getParameter("azine_email_web_view_retention");
        $response = $this->renderResponse("AzineEmailBundle:Webview:mail.not.available.html.twig", array('days' => $days));
        $response->setStatusCode(404);

        return $response;
    }

    /**
     * Check if the user is allowed to see the email.
     * => the mail is public or the user is among the recipients or the user is an admin.
     *
     * @param  SentEmail $mail
     * @return boolean
     */
    private function userIsAllowedToSeeThisMail(SentEmail $mail)
    {
        $recipients = $mail->getRecipients();

        // it is a public email
        if ($recipients === null) {
            return true;
        }

        // get the current user
        $currentUser = null;
        if (!$this->has('security.token_storage')) {
            // @codeCoverageIgnoreStart
            throw new \LogicException('The SecurityBundle is not registered in your application.');
            // @codeCoverageIgnoreEnd

        } else {
            $token = $this->get('security.token_storage')->getToken();

            // check if the token is not null and the user in the token an object
            if ($token instanceof TokenInterface && is_object($token->getUser())) {
                $currentUser = $token->getUser();
            }
        }

        // it is not a public email, and a user is logged in
        if ($currentUser !== null) {

            // the user is among the recipients
            if(array_search($currentUser->getEmail(), $recipients) !== false)

                return true;

            // the user is admin
            if($currentUser->hasRole("ROLE_ADMIN"))

                return true;
        }

        // not public email, but
        // 		- there is no user, or
        //		- the user is not among the recipients and
        //		- the user not an admin-user either
        return false;
    }

    /**
     * Replace all unmanaged Objects in the array (recursively)
     * by managed Entities fetched via Doctrine EntityManager.
     *
     *  It is assumed that managed objects can be identified
     *  by their id and implement the function getId() to get that id.
     *
     * @param  array $vars passed by reference & manipulated but not returned.
     * @return null
     */
    private function reAttachAllEntities(array &$vars)
    {
        $em = $this->get('doctrine')->getManager();
        foreach ($vars as $key => $next) {
            if (is_object($next) && method_exists($next, 'getId')) {
                $className = get_class($next);
                $managedEntity = $em->find($className, $next->getId());
                if ($managedEntity) {
                    $vars[$key] = $managedEntity;
                }
                continue;
            } elseif (is_array($next)) {
                $this->reAttachAllEntities($next);
                continue;
            }
        }

    }

    /**
     * Serve the image from the templates-folder
     * @param Request $request
     * @param  string $folderKey
     * @param  string $filename
     * @return BinaryFileResponse
     */
    public function serveImageAction(Request $request, $folderKey, $filename)
    {
        $folder = $this->getTemplateProviderService()->getFolderFrom($folderKey);
        if ($folder !== false) {
            $fullPath = $folder.$filename;
            $response = BinaryFileResponse::create($fullPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            $response->headers->set("Content-Type", "image");

            return $response;
        }

        throw new FileNotFoundException($filename);
    }

    /**
     * @return TemplateProviderInterface
     */
    protected function getTemplateProviderService()
    {
        return $this->get('azine_email_template_provider');
    }

    /**
     * @param  string   $view
     * @param  array    $parameters
     * @param  Response $response
     * @return Response
     */
    protected function renderResponse($view, array $parameters = array(), Response $response = null)
    {
        return $this->get('templating')->renderResponse($view, $parameters, $response);
    }

    /**
     * Get the sent email from the database
     * @param  string    $token the token identifying the sent email
     * @return SentEmail
     */
    protected function getSentEmailForToken($token)
    {
        $sentEmail = $this->get('doctrine')->getRepository('AzineEmailBundle:SentEmail')->findOneByToken($token);

        return $sentEmail;
    }

    /**
     * Send a test-mail for the template to the given email-address
     * @param Request $request
     * @param  string $template templateId without ending => AzineEmailBundle::baseEmailLayout (without .txt.twig)
     * @param  string $email
     * @return RedirectResponse
     */
    public function sendTestEmailAction(Request $request, $template, $email)
    {
        $locale = $request->getLocale();

        // get the email-vars for email-sending => absolute fs-paths to images
        $emailVars = $this->get('azine_email_web_view_service')->getDummyVarsFor($template, $locale);

        // send the mail
        $message = \Swift_Message::newInstance();
        $mailer = $this->get("azine_email_template_twig_swift_mailer");
        $sent = $mailer->sendSingleEmail($email, "Test Recipient", $emailVars['subject'], $emailVars, $template.".txt.twig", $locale, $emailVars['sendMailAccountAddress'], $emailVars['sendMailAccountName']." (Test)", $message);

        $flashBag = $request->getSession()->getFlashBag();

        $spamReport = $this->getSpamIndexReportForSwiftMessage($message);
        if (is_array($spamReport)) {
            if ($spamReport['curlHttpCode'] == 200 && $spamReport['success']) {
                $spamScore = $spamReport['score'];
                $spamInfo = "SpamScore: $spamScore! \n".$spamReport['report'];
            } else {
                //@codeCoverageIgnoreStart
                // this only happens if the spam-check-server has a problem / is not responding
                $spamScore = 10;
                $spamInfo = "Getting the spam-info failed.
                             HttpCode: ".$spamReport['curlHttpCode']."
                             SpamReportMsg: ".$spamReport['message'];
                if(array_key_exists('curlError', $spamReport)) {
                    $spamInfo .= "
                             cURL-Error: " . $spamReport['curlError'];
                }
                //@codeCoverageIgnoreEnd
            }

            if ($spamScore <= 2) {
                $flashBag->add('info', $spamInfo);
            } elseif ($spamScore > 2 && $spamScore < 5) {
                $flashBag->add('warn', $spamInfo);
            } else {
                $flashBag->add('error', $spamInfo);
            }

        }

        // inform about sent/failed emails
        if ($sent) {
            $msg = $this->get('translator')->trans('web.pre.view.test.mail.sent.for.%template%.to.%email%', array('%template%' => $template, '%email%' => $email));
            $flashBag->add('info', $msg);

            //@codeCoverageIgnoreStart
        } else {
            // this only happens if the mail-server has a problem
            $msg = $this->get('translator')->trans('web.pre.view.test.mail.failed.for.%template%.to.%email%', array('%template%' => $template, '%email%' => $email));
            $flashBag->add('warn', $msg);
            //@codeCoverageIgnoreStart
        }

        // show the index page again.
        return new RedirectResponse($this->get('router')->generate('azine_email_template_index', array('customEmail' => $email)));
    }

    /**
     * Make an RESTful call to http://spamcheck.postmarkapp.com/filter to test the emails-spam-index.
     * See http://spamcheck.postmarkapp.com/doc
     * @return array TestResult array('success', 'message', 'curlHttpCode', 'curlError', ['score', 'report'])
     */
    public function getSpamIndexReportForSwiftMessage(\Swift_Message $message, $report = 'long')
    {
        return $this->getSpamIndexReport($message->toString(), $report);
    }

    /**
     * @param $msgString
     * @param string $report
     * @return mixed
     */
    private function getSpamIndexReport($msgString, $report = 'long')
    {
        // check if cURL is loaded/available
        if (!function_exists('curl_init')) {
            // @codeCoverageIgnoreStart
            return array(   "success" => false,
                            "curlHttpCode" => "-",
                            "curlError" => "-",
                            "message" => "No Spam-Check done. cURL module is not available.",
                    );
            // @codeCoverageIgnoreEnd
        }

        $ch = curl_init("http://spamcheck.postmarkapp.com/filter");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $data = array("email" => $msgString, "options" => $report);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // max wait for 5sec for reply

        $result = json_decode(curl_exec($ch), true);
        $error = curl_error($ch);
        $result['curlHttpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (strlen($error) > 0) {
            $result['curlError'] = $error;
        }

        if (!array_key_exists("message", $result)) {
            $result['message'] = "-";
        }

        if (!array_key_exists('success', $result)) {
            $result['message'] = "Something went wrong! Here's the content of the curl-reply:\n\n".nl2br(print_r($result, true));

        } elseif (!$result['success'] && strpos($msgString, "Content-Transfer-Encoding: base64") !== false) {
            $result['message'] = $result['message']."\n\nRemoving the base64-Encoded Mime-Parts might help.";

        }

        return $result;

    }

    /**
     * Ajax action to check the spam-score for the pasted email-source
     */
    public function checkSpamScoreOfSentEmailAction(Request $request)
    {
        $msgString = $request->get('emailSource');
        $spamReport = $this->getSpamIndexReport($msgString);
        $spamInfo = "";
        if (is_array($spamReport)) {
            if (array_key_exists('curlHttpCode', $spamReport) && $spamReport['curlHttpCode'] == 200 && $spamReport['success'] && array_key_exists('score', $spamReport)) {
                $spamScore = $spamReport['score'];
                $spamInfo = "SpamScore: $spamScore! \n".$spamReport['report'];
                //@codeCoverageIgnoreStart
                // this only happens if the spam-check-server has a problem / is not responding
            } else {
                if( array_key_exists('curlHttpCode', $spamReport) && array_key_exists('curlError', $spamReport) && array_key_exists('message', $spamReport)){
                    $spamInfo = "Getting the spam-info failed.
                    HttpCode: " . $spamReport['curlHttpCode'] . "
                    cURL-Error: " . $spamReport['curlError'] . "
                    SpamReportMsg: " . $spamReport['message'];

                } elseif ($spamReport !== null && is_array($spamReport)) {
                    $spamInfo = "Getting the spam-info failed. This was returned:
---Start----------------------------------------------
" . implode(";\n", $spamReport) ."
---End------------------------------------------------";
                }
                //@codeCoverageIgnoreEnd
            }
        }

        return new JsonResponse(array('result' => $spamInfo));
    }
}
