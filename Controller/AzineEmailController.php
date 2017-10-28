<?php

namespace Azine\EmailBundle\Controller;

use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Form\SentEmailType;
use FOS\UserBundle\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * This controller provides the following actions:
 *
 * emailsDashboard: a list of all SentEmail entities with ability to filter by each property.
 * emailDetailsByToken: extended view of SentEmail entity searched by a token property.
 */
class AzineEmailController extends Controller
{
    /**
     *  Displays an Emails-Dashboard with filters for each property of SentEmails entity and links to
     *  emailDetailsByToken & webView actions for each email
     */
    public function emailsDashboardAction(Request $request)
    {
        $form = $this->createForm(new SentEmailType());
        $form->handleRequest($request);
        $searchParams = $form->getData();
        $repository = $this->getDoctrine()->getManager()->getRepository(SentEmail::class);

        $emails = $repository->search($searchParams);
        $emailsArray = [];

        foreach ($emails as $key => $email){

            $emailsArray[$key]['recipients'] = substr(implode(', ', $email->getRecipients()), 0, 60);
            $emailsArray[$key]['template'] = $email->getTemplate();
            $emailsArray[$key]['sent'] = $email->getSent()->format('Y-m-d H:i:s');
            $emailsArray[$key]['variables'] = substr(json_encode($email->getVariables()), 0, 60);
            $emailsArray[$key]['token'] = $email->getToken();
        }

        $pagination = $this->get('knp_paginator')->paginate($emailsArray, $request->query->get('page', 1));

        return $this->render('AzineEmailBundle::emailsDashboard.html.twig',
            ['form' => $form->createView(), 'pagination' => $pagination ]);
    }

    /**
     *  Displays an extended view of SentEmail entity searched by a token property
     * @param string $token
     * @return Response
     */
    public function emailDetailsByTokenAction(Request $request, $token)
    {
        $email = $this->getDoctrine()->getManager()->getRepository(SentEmail::class)
            ->findOneByToken($token);

        if($email instanceof SentEmail){

            $recipients = implode(', ', $email->getRecipients());
            $variables = implode(', ', array_keys($email->getVariables()));

            return $this->render('AzineEmailBundle::sentEmailDetails.html.twig',
                ['email' => $email, 'recipients' => $recipients, 'variables' => $variables]);
        }

        // the parameters-array is null => the email is not available in webView
        $days = $this->getParameter("azine_email_web_view_retention");
        $response = $this->render("AzineEmailBundle:Webview:mail.not.available.html.twig", array('days' => $days));
        $response->setStatusCode(404);

        return $response;
    }
}
